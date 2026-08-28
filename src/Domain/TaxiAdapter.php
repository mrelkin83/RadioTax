<?php

declare(strict_types=1);

namespace TaxiApp\Domain;

use PDO;
use RuntimeException;
use TaxiApp\Capacidades\SoportaDespachoOperativo;
use TaxiApp\Capacidades\SoportaDireccionesFrecuentes;
use TaxiApp\Core\Database;

/**
 * Pendiente: `implements \ElkinLinan\WhatsappAiEngine\Contracts\DomainAdapter`
 * en cuanto el paquete esté declarado en composer.json (ver docs/ESTADO_Y_PENDIENTES.md).
 * Los nombres y firmas de método ya siguen el mapeo documentado en
 * SYSTEM_PROMPT_MAESTRO_TAXIAPP.md §5.2.
 */
final class TaxiAdapter implements SoportaDireccionesFrecuentes, SoportaDespachoOperativo
{
    public function __construct(private readonly ?PDO $db = null)
    {
    }

    private function conexion(): PDO
    {
        return $this->db ?? Database::conexion();
    }

    public function contextoCliente(string $whatsapp, int $empresaId): array
    {
        $sentencia = $this->conexion()->prepare(
            'SELECT * FROM tx_clientes WHERE empresa_id = :empresa AND whatsapp = :whatsapp LIMIT 1'
        );
        $sentencia->execute(['empresa' => $empresaId, 'whatsapp' => $whatsapp]);
        $cliente = $sentencia->fetch();

        if ($cliente === false) {
            return ['cliente' => null, 'direcciones_frecuentes' => [], 'historial' => []];
        }

        return [
            'cliente' => $cliente,
            'direcciones_frecuentes' => $this->listar((int) $cliente['id']),
            'historial' => $this->historialCarreras((int) $cliente['id']),
        ];
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

    public function buscarItems(int $empresaId): array
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

    public function detalleItem(int $empresaId, string $codigo): ?array
    {
        foreach ($this->buscarItems($empresaId) as $item) {
            if (($item['codigo'] ?? null) === $codigo) {
                return $item;
            }
        }

        return null;
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

    public function disponibilidad(int $empresaId, string $tipoServicio): ?int
    {
        $empresa = $this->empresa($empresaId);
        if (($empresa['config']['exponer_disponibilidad'] ?? false) !== true) {
            return null;
        }

        $sentencia = $this->conexion()->prepare(
            "SELECT COUNT(*) FROM tx_vehiculos WHERE empresa_id = :empresa AND tipo = :tipo AND estado_vehiculo = 'DISPONIBLE'"
        );
        $sentencia->execute(['empresa' => $empresaId, 'tipo' => $tipoServicio]);

        return (int) $sentencia->fetchColumn();
    }

    public function crearTransaccion(array $datos): array
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
        $this->registrarEvento($carreraId, 'CARRERA_RECIBIDA', 'IA', null, ['tipo_servicio' => $datos['tipo_servicio']]);

        return $this->estadoTransaccion($carreraId);
    }

    private function modoDespacho(int $empresaId): string
    {
        $sentencia = $this->conexion()->prepare('SELECT modo FROM tx_config_despacho WHERE empresa_id = :empresa');
        $sentencia->execute(['empresa' => $empresaId]);
        $modo = $sentencia->fetchColumn();

        return $modo !== false ? $modo : $this->empresa($empresaId)['modo_despacho_default'];
    }

    public function estadoTransaccion(int $carreraId): array
    {
        $sentencia = $this->conexion()->prepare('SELECT * FROM tx_carreras WHERE id = :id LIMIT 1');
        $sentencia->execute(['id' => $carreraId]);
        $carrera = $sentencia->fetch();

        if ($carrera === false) {
            throw new RuntimeException("Carrera {$carreraId} no existe.");
        }

        return $carrera;
    }

    public function cancelarTransaccion(int $carreraId, string $motivo, string $actorTipo = 'IA', ?int $actorId = null): bool
    {
        $carrera = $this->estadoTransaccion($carreraId);
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

    public function confirmarTransaccion(int $carreraId): bool
    {
        $carrera = $this->estadoTransaccion($carreraId);
        if (!in_array($carrera['estado'], ['RECIBIDA', 'DATOS_COMPLETOS'], true)) {
            throw new RuntimeException("La carrera {$carreraId} no se puede enviar a despacho desde el estado {$carrera['estado']}.");
        }

        $sentencia = $this->conexion()->prepare('UPDATE tx_carreras SET estado = "EN_DESPACHO" WHERE id = :id');
        $sentencia->execute(['id' => $carreraId]);

        $this->registrarEvento($carreraId, 'ENVIADA_A_DESPACHO', 'SISTEMA', null, []);

        return true;
    }

    public function calcularTotal(int $carreraId): array
    {
        $carrera = $this->estadoTransaccion($carreraId);

        return [
            'carrera_id' => $carrera['id'],
            'monto' => null,
            'mensaje' => 'El valor lo acuerda con el conductor.',
        ];
    }

    public function capacidades(): array
    {
        return [
            'consultar_tipos_servicio' => true,
            'consultar_direcciones_frecuentes' => true,
            'registrar_solicitud' => true,
            'consultar_estado_carrera' => true,
            'cancelar_carrera' => true,
            'transferir_a_humano' => true,
            'pagos' => false,
            'promociones' => false,
            'menu_del_dia' => false,
            'credito' => false,
            'servicio_tecnico' => false,
        ];
    }

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

    public function estadoDeCarrera(int $carreraId): array
    {
        return $this->estadoTransaccion($carreraId);
    }

    public function candidatosDisponibles(int $empresaId, string $tipoServicio): array
    {
        $sentencia = $this->conexion()->prepare(
            "SELECT * FROM tx_vehiculos WHERE empresa_id = :empresa AND tipo = :tipo AND estado_vehiculo = 'DISPONIBLE'"
        );
        $sentencia->execute(['empresa' => $empresaId, 'tipo' => $tipoServicio]);

        return $sentencia->fetchAll();
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
}
