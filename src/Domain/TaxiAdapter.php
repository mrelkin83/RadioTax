<?php

declare(strict_types=1);

namespace TaxiApp\Domain;

use ElkinLinan\WhatsappAiEngine\Engine;
use ElkinLinan\WhatsappAiEngine\Ports\DomainAdapter;
use ElkinLinan\WhatsappAiEngine\Ports\SinCatalogoDeProductos;
use ElkinLinan\WhatsappAiEngine\Ports\SoportaHerramientasPersonalizadas;
use LogicException;
use PDO;
use RuntimeException;
use TaxiApp\Capacidades\SoportaDespachoOperativo;
use TaxiApp\Capacidades\SoportaDireccionesFrecuentes;
use TaxiApp\Core\Database;

/**
 * La frontera entre el motor y el dominio taxi.
 *
 * TAXI no vende productos con cantidad — vende viajes con recogida y destino —
 * así que implementa SinCatalogoDeProductos para apagar las nueve herramientas
 * de "carrito de compra" del motor (consultar_menu, crear_pedido,
 * calcular_total...) y SoportaHerramientasPersonalizadas para ofrecer las
 * suyas (registrar_solicitud, consultar_estado_carrera...). Ver
 * docs/ARQUITECTURA_Y_MODELO_DE_DATOS.md para el porqué completo.
 *
 * De los métodos que exige DomainAdapter, solo `contextoCliente()` y
 * `capacidades()` son alcanzables de verdad (el motor los llama siempre). El
 * resto (buscarItems, crearTransaccion, calcularTotal...) solo se alcanzan
 * desde las herramientas apagadas por SinCatalogoDeProductos, así que aquí son
 * o bien delegados a los métodos reales de este dominio (cuando la firma solo
 * cambia de tipo, ej. string→int), o bien un `throw` explícito cuando la forma
 * no tiene traducción honesta (un carrito de productos no es un viaje).
 */
final class TaxiAdapter implements
    DomainAdapter,
    SinCatalogoDeProductos,
    SoportaHerramientasPersonalizadas,
    SoportaDireccionesFrecuentes,
    SoportaDespachoOperativo
{
    public function __construct(private readonly ?PDO $db = null)
    {
    }

    private function conexion(): PDO
    {
        return $this->db ?? Database::conexion();
    }

    private function empresaId(): int
    {
        $id = Engine::negocio()->id();
        if ($id === null) {
            throw new RuntimeException(
                'TaxiAdapter necesita un TenantPort con empresa_id — TAXIS es multi-empresa desde el esquema.'
            );
        }

        return $id;
    }

    /* ── Identificación del cliente (§5.4 identificar_cliente / contextoCliente) ── */

    public function contextoCliente(array $conversacion): array
    {
        $clienteId = $this->resolverClienteId($conversacion);
        $cliente = $this->conexion()->prepare('SELECT * FROM tx_clientes WHERE id = :id LIMIT 1');
        $cliente->execute(['id' => $clienteId]);
        $fila = $cliente->fetch();

        return [
            'cliente_id' => $clienteId,
            'nombre' => $fila['nombre'] ?? null,
            'direcciones_frecuentes' => $this->listar($clienteId),
            'historial' => $this->historialCarreras($clienteId),
        ];
    }

    private function resolverClienteId(array $conversacion): int
    {
        $whatsapp = (string) ($conversacion['telefono'] ?? '');
        if ($whatsapp === '') {
            throw new RuntimeException('No se pudo identificar el número de WhatsApp de esta conversación.');
        }

        $empresaId = $this->empresaId();
        // Por los últimos 10 dígitos, no el número completo: un cliente
        // registrado a mano (panel, WhatsApp manual) no siempre trae el
        // indicativo de país, y el motor SIEMPRE lo manda en el JID. Un LID
        // (@lid, sin número real) sí se compara exacto — no hay dígitos que
        // recortar y dos LID solo coinciden si son el mismo identificador.
        $porSufijo = !str_contains($whatsapp, '@');
        $sentencia = $this->conexion()->prepare(
            $porSufijo
                ? 'SELECT id FROM tx_clientes WHERE empresa_id = :empresa AND RIGHT(whatsapp, 10) = RIGHT(:whatsapp, 10) LIMIT 1'
                : 'SELECT id FROM tx_clientes WHERE empresa_id = :empresa AND whatsapp = :whatsapp LIMIT 1'
        );
        $sentencia->execute(['empresa' => $empresaId, 'whatsapp' => $whatsapp]);
        $id = $sentencia->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $sentencia = $this->conexion()->prepare(
            "INSERT INTO tx_clientes (empresa_id, whatsapp, nombre, creado_por) VALUES (:empresa, :whatsapp, :nombre, 'IA')"
        );
        $sentencia->execute([
            'empresa' => $empresaId,
            'whatsapp' => $whatsapp,
            'nombre' => $conversacion['nombre_contacto'] ?? null,
        ]);

        return (int) $this->conexion()->lastInsertId();
    }

    private function historialCarreras(int $clienteId, int $limite = 5): array
    {
        $sentencia = $this->conexion()->prepare(
            'SELECT id, tipo_servicio, recogida_texto, destino_texto, estado, creado_en
             FROM tx_carreras WHERE cliente_id = :cliente ORDER BY creado_en DESC LIMIT :limite'
        );
        $sentencia->bindValue('cliente', $clienteId, PDO::PARAM_INT);
        $sentencia->bindValue('limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /* ── Catálogo de tipos de servicio (§5.4 consultar_tipos_servicio) ── */

    private function tiposServicio(int $empresaId): array
    {
        $tipos = $this->empresa($empresaId)['config']['tipos_servicio'] ?? null;

        if (!is_array($tipos) || $tipos === []) {
            return [
                ['codigo' => 'TAXI', 'nombre' => 'Taxi público'],
                ['codigo' => 'CARGA', 'nombre' => 'Transporte de carga'],
            ];
        }

        return $tipos;
    }

    private function empresa(int $empresaId): array
    {
        $sentencia = $this->conexion()->prepare('SELECT * FROM tx_empresas WHERE id = :id LIMIT 1');
        $sentencia->execute(['id' => $empresaId]);
        $empresa = $sentencia->fetch();

        if ($empresa === false) {
            throw new RuntimeException("Empresa {$empresaId} no existe.");
        }

        $empresa['config'] = $empresa['config'] !== null ? json_decode((string) $empresa['config'], true) : [];

        return $empresa;
    }

    /* ── La carrera: lógica real, con nombres propios para no chocar con las
       firmas de DomainAdapter (que están pensadas para un carrito de
       productos, no para un viaje) ── */

    public function crearCarrera(array $datos, string $actorTipo = 'IA'): array
    {
        foreach (['empresa_id', 'linea_id', 'cliente_id', 'conversacion_ref', 'tipo_servicio', 'recogida_texto', 'destino_texto'] as $campo) {
            if (empty($datos[$campo])) {
                throw new RuntimeException("Falta el campo requerido: {$campo}");
            }
        }

        $hash = hash('sha256', implode('|', [
            $datos['conversacion_ref'],
            $datos['cliente_id'],
            $datos['tipo_servicio'],
            $datos['recogida_texto'],
            $datos['destino_texto'],
        ]));

        $existente = $this->conexion()->prepare('SELECT * FROM tx_carreras WHERE idempotencia_hash = :hash LIMIT 1');
        $existente->execute(['hash' => $hash]);
        $fila = $existente->fetch();
        if ($fila !== false) {
            return $fila;
        }

        $insertar = $this->conexion()->prepare(
            'INSERT INTO tx_carreras
                (empresa_id, linea_id, conversacion_ref, cliente_id, tipo_servicio,
                 recogida_texto, recogida_lat, recogida_lng, destino_texto, destino_lat, destino_lng,
                 observaciones, estado, modo_despacho_usado, idempotencia_hash)
             VALUES
                (:empresa_id, :linea_id, :conversacion_ref, :cliente_id, :tipo_servicio,
                 :recogida_texto, :recogida_lat, :recogida_lng, :destino_texto, :destino_lat, :destino_lng,
                 :observaciones, "RECIBIDA", :modo, :hash)'
        );
        $insertar->execute([
            'empresa_id' => $datos['empresa_id'],
            'linea_id' => $datos['linea_id'],
            'conversacion_ref' => $datos['conversacion_ref'],
            'cliente_id' => $datos['cliente_id'],
            'tipo_servicio' => $datos['tipo_servicio'],
            'recogida_texto' => $datos['recogida_texto'],
            'recogida_lat' => $datos['recogida_lat'] ?? null,
            'recogida_lng' => $datos['recogida_lng'] ?? null,
            'destino_texto' => $datos['destino_texto'],
            'destino_lat' => $datos['destino_lat'] ?? null,
            'destino_lng' => $datos['destino_lng'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'modo' => $this->modoDespacho((int) $datos['empresa_id']),
            'hash' => $hash,
        ]);

        $carreraId = (int) $this->conexion()->lastInsertId();
        $this->registrarEvento($carreraId, 'CARRERA_RECIBIDA', $actorTipo, $datos['actor_id'] ?? null, ['tipo_servicio' => $datos['tipo_servicio']]);

        return $this->estadoCarrera($carreraId);
    }

    private function modoDespacho(int $empresaId): string
    {
        $sentencia = $this->conexion()->prepare('SELECT modo FROM tx_config_despacho WHERE empresa_id = :empresa');
        $sentencia->execute(['empresa' => $empresaId]);
        $modo = $sentencia->fetchColumn();

        return $modo !== false ? $modo : $this->empresa($empresaId)['modo_despacho_default'];
    }

    public function estadoCarrera(int $carreraId): array
    {
        $sentencia = $this->conexion()->prepare('SELECT * FROM tx_carreras WHERE id = :id LIMIT 1');
        $sentencia->execute(['id' => $carreraId]);
        $carrera = $sentencia->fetch();

        if ($carrera === false) {
            throw new RuntimeException("Carrera {$carreraId} no existe.");
        }

        return $carrera;
    }

    public function cancelarCarrera(int $carreraId, string $motivo, string $actorTipo = 'IA', ?int $actorId = null): bool
    {
        $carrera = $this->estadoCarrera($carreraId);
        if (in_array($carrera['estado'], ['EN_SERVICIO', 'FINALIZADA', 'CANCELADA'], true)) {
            throw new RuntimeException("La carrera {$carreraId} no se puede cancelar en estado {$carrera['estado']}.");
        }

        $sentencia = $this->conexion()->prepare(
            'UPDATE tx_carreras SET estado = "CANCELADA", motivo_cierre = :motivo WHERE id = :id'
        );
        $sentencia->execute(['motivo' => $motivo, 'id' => $carreraId]);

        $this->registrarEvento($carreraId, 'CARRERA_CANCELADA', $actorTipo, $actorId, ['motivo' => $motivo]);

        return true;
    }

    public function confirmarCarrera(int $carreraId): bool
    {
        $carrera = $this->estadoCarrera($carreraId);
        if (!in_array($carrera['estado'], ['RECIBIDA', 'DATOS_COMPLETOS'], true)) {
            throw new RuntimeException("La carrera {$carreraId} no se puede enviar a despacho desde el estado {$carrera['estado']}.");
        }

        $sentencia = $this->conexion()->prepare('UPDATE tx_carreras SET estado = "EN_DESPACHO" WHERE id = :id');
        $sentencia->execute(['id' => $carreraId]);

        $this->registrarEvento($carreraId, 'ENVIADA_A_DESPACHO', 'SISTEMA', null, []);

        return true;
    }

    /** El valor lo pone el conductor (regla §2 del system prompt maestro): nunca un monto. */
    public function calcularTotalCarrera(int $carreraId): array
    {
        $carrera = $this->estadoCarrera($carreraId);

        return [
            'carrera_id' => $carrera['id'],
            'monto' => null,
            'mensaje' => 'El valor lo acuerda con el conductor.',
        ];
    }

    private function registrarEvento(int $carreraId, string $evento, string $actorTipo, ?int $actorId, array $detalle): void
    {
        $sentencia = $this->conexion()->prepare(
            'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
             VALUES (:carrera, :evento, :actor_tipo, :actor_id, :detalle)'
        );
        $sentencia->execute([
            'carrera' => $carreraId,
            'evento' => $evento,
            'actor_tipo' => $actorTipo,
            'actor_id' => $actorId,
            'detalle' => json_encode($detalle, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /* ── SoportaDireccionesFrecuentes (§5.3) ── */

    public function listar(int $clienteId): array
    {
        $sentencia = $this->conexion()->prepare(
            'SELECT * FROM tx_direcciones WHERE cliente_id = :cliente ORDER BY veces_usada DESC'
        );
        $sentencia->execute(['cliente' => $clienteId]);

        return $sentencia->fetchAll();
    }

    public function guardar(int $clienteId, string $etiqueta, string $texto, ?string $barrioZona = null): void
    {
        $sentencia = $this->conexion()->prepare(
            'INSERT INTO tx_direcciones (cliente_id, etiqueta, texto, barrio_zona, veces_usada)
             VALUES (:cliente, :etiqueta, :texto, :zona, 1)'
        );
        $sentencia->execute([
            'cliente' => $clienteId,
            'etiqueta' => $etiqueta,
            'texto' => $texto,
            'zona' => $barrioZona,
        ]);
    }

    /* ── SoportaDespachoOperativo (§5.3) ── */

    public function estadoDeCarrera(int $carreraId): array
    {
        return $this->estadoCarrera($carreraId);
    }

    public function candidatosDisponibles(int $empresaId, string $tipoServicio): array
    {
        $sentencia = $this->conexion()->prepare(
            "SELECT * FROM tx_vehiculos WHERE empresa_id = :empresa AND tipo = :tipo AND estado_vehiculo = 'DISPONIBLE'"
        );
        $sentencia->execute(['empresa' => $empresaId, 'tipo' => $tipoServicio]);

        return $sentencia->fetchAll();
    }

    /* ══════════════════════════════════════════════════════════════════════
       DomainAdapter — lo que exige el motor. Solo contextoCliente() (arriba)
       y capacidades() son alcanzables en la práctica: el resto cuelga de las
       nueve herramientas que SinCatalogoDeProductos apaga.
       ══════════════════════════════════════════════════════════════════════ */

    public function capacidades(): array
    {
        // TAXI no usa ninguna de las capacidades opcionales del vocabulario del
        // motor (entrega/promociones/menu_del_dia/garantias/servicio_tecnico/
        // credito/...): todas sus herramientas propias van sin 'capacidad',
        // habilitadas siempre que el agente las tenga en su lista blanca.
        return [];
    }

    public function buscarItems(?string $busqueda = null, array $filtros = [], int $limite = 60): array
    {
        return $this->tiposServicio($this->empresaId());
    }

    public function detalleItem(string $id): ?array
    {
        foreach ($this->tiposServicio($this->empresaId()) as $item) {
            if (($item['codigo'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /** No aplica: TAXI no controla "unidades" de un tipo de servicio. */
    public function disponibilidad(string $id): ?int
    {
        return null;
    }

    /**
     * No hay traducción honesta: el motor piensa esto como un carrito de
     * productos con cantidad, y un viaje no lo es (una recogida y un destino,
     * no "N unidades de taxi"). Inalcanzable en la práctica (ver
     * SinCatalogoDeProductos); si algo llega aquí es un error de programación
     * en el motor o en cómo se lo llamó, no un caso de negocio real.
     */
    public function crearTransaccion(array $conversacion, array $items, array $datos = []): array
    {
        throw new LogicException(
            'crearTransaccion() no aplica en TAXI (no vende productos con cantidad). '
            . 'Usa la herramienta registrar_solicitud, que llama a crearCarrera().'
        );
    }

    /** Mismo motivo que crearTransaccion(): un viaje no es un carrito. Inalcanzable. */
    public function calcularTotal(array $items, float $extra = 0.0): array
    {
        throw new LogicException(
            'calcularTotal() no aplica en TAXI. El valor de la carrera lo acuerda el cliente con el conductor '
            . '(regla §2 del system prompt maestro): nunca lo calcula el sistema.'
        );
    }

    public function estadoTransaccion(string $id): array
    {
        return $this->estadoCarrera((int) $id);
    }

    /**
     * No hay conversacion_id propio en el esquema tx_* (ese id es del motor,
     * de wa_conversaciones). Inalcanzable en la práctica: consultar_estado_carrera
     * ya resuelve "mis carreras abiertas" a partir del cliente identificado.
     */
    public function transaccionesDe(int $conversacionId): array
    {
        return [];
    }

    public function cancelarTransaccion(string $id): array
    {
        $ok = $this->cancelarCarrera((int) $id, 'Cancelada por el cliente vía WhatsApp', 'IA');

        return ['ok' => $ok];
    }

    public function confirmarTransaccion(string $id): bool
    {
        return $this->confirmarCarrera((int) $id);
    }

    /* ══════════════════════════════════════════════════════════════════════
       SoportaHerramientasPersonalizadas — el catálogo real de TAXI (§5.4).
       ══════════════════════════════════════════════════════════════════════ */

    public function herramientasPersonalizadas(): array
    {
        return [
            'identificar_cliente' => [
                'description' => 'Reconoce al cliente por su número de WhatsApp y trae lo que ya sabemos de él: nombre y direcciones guardadas. Llámala al inicio para no volver a preguntar lo que ya se sabe.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            ],
            'consultar_tipos_servicio' => [
                'description' => 'Los tipos de servicio que ofrece esta empresa ahora mismo (taxi, carga, ...).',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            ],
            'consultar_direcciones_frecuentes' => [
                'description' => 'Direcciones guardadas del cliente de esta conversación (casa, trabajo...). Úsala antes de preguntar la dirección: si el cliente tiene una guardada, confírmasela en vez de pedírsela de nuevo.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            ],
            'registrar_solicitud' => [
                'description' => 'Crea la solicitud de servicio (la carrera) con los datos que ya confirmó el cliente. Llámala solo cuando tengas tipo de servicio, recogida y destino — nunca inventes ninguno.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'tipo_servicio' => ['type' => 'string', 'description' => 'Código del tipo de servicio, tal como lo devolvió consultar_tipos_servicio'],
                    'recogida_texto' => ['type' => 'string'],
                    'destino_texto' => ['type' => 'string'],
                    'observaciones' => ['type' => 'string'],
                ], 'required' => ['tipo_servicio', 'recogida_texto', 'destino_texto']],
            ],
            'consultar_estado_carrera' => [
                'description' => 'Estado en vivo de una carrera de este cliente (asignada, en camino...). Sin carrera_id, devuelve las carreras abiertas de esta conversación.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'carrera_id' => ['type' => 'integer'],
                ], 'required' => []],
            ],
            'cancelar_carrera' => [
                'description' => 'Cancela una carrera de este cliente, con el motivo que dé. Solo funciona antes de que el vehículo esté en servicio.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'carrera_id' => ['type' => 'integer'],
                    'motivo' => ['type' => 'string'],
                ], 'required' => ['carrera_id', 'motivo']],
            ],
        ];
    }

    public function ejecutarHerramientaPersonalizada(string $nombre, array $args, array $ctx): ?array
    {
        $conversacion = $ctx['conversacion'] ?? [];

        return match ($nombre) {
            'identificar_cliente' => ['ok' => true] + $this->contextoCliente($conversacion),
            'consultar_tipos_servicio' => ['ok' => true, 'tipos_servicio' => $this->tiposServicio($this->empresaId())],
            'consultar_direcciones_frecuentes' => [
                'ok' => true,
                'direcciones' => $this->listar($this->resolverClienteId($conversacion)),
            ],
            'registrar_solicitud' => $this->herramientaRegistrarSolicitud($conversacion, $args),
            'consultar_estado_carrera' => $this->herramientaConsultarEstadoCarrera($conversacion, $args),
            'cancelar_carrera' => $this->herramientaCancelarCarrera($conversacion, $args),
            default => null,
        };
    }

    private function herramientaRegistrarSolicitud(array $conversacion, array $args): array
    {
        $tipoServicio = trim((string) ($args['tipo_servicio'] ?? ''));
        $recogida = trim((string) ($args['recogida_texto'] ?? ''));
        $destino = trim((string) ($args['destino_texto'] ?? ''));
        $observaciones = trim((string) ($args['observaciones'] ?? ''));

        if ($tipoServicio === '' || $recogida === '' || $destino === '') {
            return ['ok' => false, 'error' => 'Faltan datos: tipo de servicio, recogida y destino son obligatorios.'];
        }

        $empresaId = $this->empresaId();
        $clienteId = $this->resolverClienteId($conversacion);
        $lineaId = $conversacion['linea_id'] ?? $this->primeraLineaDe($empresaId);
        if ($lineaId === null) {
            return ['ok' => false, 'error' => 'Esta empresa no tiene líneas de WhatsApp configuradas.'];
        }

        try {
            $carrera = $this->crearCarrera([
                'empresa_id' => $empresaId,
                'linea_id' => $lineaId,
                'cliente_id' => $clienteId,
                'conversacion_ref' => 'wa-conv-' . ($conversacion['id'] ?? uniqid('', true)),
                'tipo_servicio' => $tipoServicio,
                'recogida_texto' => $recogida,
                'destino_texto' => $destino,
                'observaciones' => $observaciones !== '' ? $observaciones : null,
            ], 'IA');
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'carrera_id' => $carrera['id'],
            'estado' => $carrera['estado'],
            'mensaje_para_el_cliente' => 'Ya registré tu solicitud. En un momento te confirmo el vehículo asignado.',
        ];
    }

    private function primeraLineaDe(int $empresaId): ?int
    {
        $sentencia = $this->conexion()->prepare('SELECT id FROM tx_lineas WHERE empresa_id = :empresa ORDER BY id ASC LIMIT 1');
        $sentencia->execute(['empresa' => $empresaId]);
        $id = $sentencia->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function herramientaConsultarEstadoCarrera(array $conversacion, array $args): array
    {
        $clienteId = $this->resolverClienteId($conversacion);

        if (!empty($args['carrera_id'])) {
            $carrera = $this->estadoCarrera((int) $args['carrera_id']);
            if ((int) $carrera['cliente_id'] !== $clienteId) {
                return ['ok' => false, 'error' => 'Esa carrera no es de este cliente'];
            }

            return ['ok' => true, 'carrera' => $carrera];
        }

        $sentencia = $this->conexion()->prepare(
            "SELECT * FROM tx_carreras WHERE cliente_id = :cliente
             AND estado NOT IN ('FINALIZADA','CANCELADA','NO_ATENDIDA') ORDER BY creado_en DESC"
        );
        $sentencia->execute(['cliente' => $clienteId]);

        return ['ok' => true, 'carreras' => $sentencia->fetchAll()];
    }

    private function herramientaCancelarCarrera(array $conversacion, array $args): array
    {
        $carreraId = (int) ($args['carrera_id'] ?? 0);
        $motivo = trim((string) ($args['motivo'] ?? ''));
        if ($carreraId <= 0 || $motivo === '') {
            return ['ok' => false, 'error' => 'Faltan datos: carrera_id y motivo son obligatorios.'];
        }

        $clienteId = $this->resolverClienteId($conversacion);
        $carrera = $this->estadoCarrera($carreraId);
        if ((int) $carrera['cliente_id'] !== $clienteId) {
            return ['ok' => false, 'error' => 'Esa carrera no es de este cliente'];
        }

        try {
            $this->cancelarCarrera($carreraId, $motivo, 'IA');
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'cancelada' => true];
    }
}
