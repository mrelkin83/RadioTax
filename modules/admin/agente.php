<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Core\AgentManager;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;
use TaxiApp\Core\Database;

$empresaId = $usuarioActual['empresa_id'];
$error = null;
$guardadoSeccion = null;

ConectorMotor::conectar($empresaId);
$db = Engine::db();
$gestor = new AgentManager($db);
$pdo = Database::conexion();

// Colombia y Venezuela por ahora — se amplía si el negocio opera en otro país.
$departamentosPorPais = [
    'Colombia' => ['Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bogotá D.C.', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá', 'Casanare', 'Cauca', 'Cesar', 'Chocó', 'Córdoba', 'Cundinamarca', 'Guainía', 'Guaviare', 'Huila', 'La Guajira', 'Magdalena', 'Meta', 'Nariño', 'Norte de Santander', 'Putumayo', 'Quindío', 'Risaralda', 'San Andrés y Providencia', 'Santander', 'Sucre', 'Tolima', 'Valle del Cauca', 'Vaupés', 'Vichada'],
    'Venezuela' => ['Amazonas', 'Anzoátegui', 'Apure', 'Aragua', 'Barinas', 'Bolívar', 'Carabobo', 'Cojedes', 'Delta Amacuro', 'Distrito Capital', 'Falcón', 'Guárico', 'La Guaira', 'Lara', 'Mérida', 'Miranda', 'Monagas', 'Nueva Esparta', 'Portuguesa', 'Sucre', 'Táchira', 'Trujillo', 'Yaracuy', 'Zulia'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? 'guardar_agente');

    if ($accion === 'guardar_ubicacion') {
        $pais = trim((string) ($_POST['pais'] ?? ''));
        $departamento = trim((string) ($_POST['departamento'] ?? ''));
        $ciudad = trim((string) ($_POST['ciudad'] ?? ''));
        if ($pais !== '' && !isset($departamentosPorPais[$pais])) {
            $pais = '';
        }
        if ($ciudad === '') {
            $error = 'La ciudad es obligatoria.';
        } else {
            $pdo->prepare('UPDATE tx_empresas SET pais = :pais, departamento = :departamento, ciudad = :ciudad WHERE id = :id')
                ->execute(['pais' => $pais ?: null, 'departamento' => $departamento ?: null, 'ciudad' => $ciudad, 'id' => $empresaId]);
            header('Location: /modules/admin/agente.php?guardado=ubicacion');
            exit;
        }
    } else {
        $genero = (string) ($_POST['genero'] ?? 'femenino');
        $genero = in_array($genero, ['femenino', 'masculino'], true) ? $genero : 'femenino';

        $gestor->guardar([
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'rol' => trim((string) ($_POST['rol'] ?? '')),
            'objetivo' => trim((string) ($_POST['objetivo'] ?? '')),
            'personalidad' => trim((string) ($_POST['personalidad'] ?? '')),
            'genero' => $genero,
            'idioma' => trim((string) ($_POST['idioma'] ?? '')) ?: 'es',
            'instrucciones' => trim((string) ($_POST['instrucciones'] ?? '')),
            'saludo_inicial' => trim((string) ($_POST['saludo_inicial'] ?? '')),
            'mensaje_fuera_horario' => trim((string) ($_POST['mensaje_fuera_horario'] ?? '')),
            'mensaje_error' => trim((string) ($_POST['mensaje_error'] ?? '')),
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ]);
        header('Location: /modules/admin/agente.php?guardado=agente');
        exit;
    }
}

$agente = $gestor->activo();

$sentenciaEmpresa = $pdo->prepare('SELECT pais, departamento, ciudad FROM tx_empresas WHERE id = :id LIMIT 1');
$sentenciaEmpresa->execute(['id' => $empresaId]);
$empresaUbicacion = $sentenciaEmpresa->fetch() ?: ['pais' => '', 'departamento' => '', 'ciudad' => ''];

if (isset($_GET['guardado'])) {
    $guardadoSeccion = (string) $_GET['guardado'];
}

$csrf = Auth::tokenCsrf();
$activo = 'agente';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agente de IA · Administración · <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-3xl mx-auto space-y-8">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-base rounded-lg px-4 py-3" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($guardadoSeccion === 'agente'): ?>
      <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-base rounded-lg px-4 py-3">Agente actualizado.</p>
    <?php elseif ($guardadoSeccion === 'ubicacion'): ?>
      <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-base rounded-lg px-4 py-3">Ubicación guardada.</p>
    <?php endif; ?>

    <p class="text-sm text-neutral-700 -mt-2">
      Esto define cómo se presenta y se comporta el asistente por WhatsApp — no reemplaza las reglas de seguridad
      del motor (esas no se pueden editar desde aquí), solo el rol, el tono y los mensajes que le corresponden a esta empresa.
    </p>

    <form method="post" class="bg-card border border-border rounded-xl p-6 space-y-6">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="accion" value="guardar_agente">

      <label class="flex items-center gap-3 text-base">
        <input type="checkbox" name="activo" <?= !empty($agente['activo']) ? 'checked' : '' ?> class="accent-accent w-5 h-5">
        Agente activo (si está apagado, el motor no responde por IA)
      </label>

      <div>
        <h3 class="text-base font-semibold text-slate-300 mb-3">Identidad</h3>
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label for="ag_nombre" class="block text-sm text-slate-400 mb-2">Nombre del agente</label>
            <input id="ag_nombre" name="nombre" placeholder="Asistente" value="<?= htmlspecialchars((string) ($agente['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
          </div>
          <div>
            <label for="ag_genero" class="block text-sm text-slate-400 mb-2">Género</label>
            <select id="ag_genero" name="genero" class="w-full rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base">
              <option value="femenino" <?= ($agente['genero'] ?? 'femenino') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
              <option value="masculino" <?= ($agente['genero'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
            </select>
          </div>
          <div>
            <label for="ag_idioma" class="block text-sm text-slate-400 mb-2">Idioma</label>
            <input id="ag_idioma" name="idioma" placeholder="es" value="<?= htmlspecialchars((string) ($agente['idioma'] ?? 'es'), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
          </div>
        </div>
      </div>

      <div>
        <h3 class="text-base font-semibold text-slate-300 mb-3">Rol y personalidad</h3>
        <div class="space-y-3">
          <div>
            <label for="ag_rol" class="block text-sm text-slate-400 mb-2">Rol</label>
            <input id="ag_rol" name="rol" placeholder="Atiendes a los clientes del negocio por WhatsApp." value="<?= htmlspecialchars((string) ($agente['rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
          </div>
          <div>
            <label for="ag_objetivo" class="block text-sm text-slate-400 mb-2">Objetivo</label>
            <input id="ag_objetivo" name="objetivo" placeholder="Resolver dudas, tomar solicitudes de taxi y confirmar la asignación." value="<?= htmlspecialchars((string) ($agente['objetivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
          </div>
          <div>
            <label for="ag_personalidad" class="block text-sm text-slate-400 mb-2">Personalidad</label>
            <input id="ag_personalidad" name="personalidad" placeholder="Amable, cercano y directo." value="<?= htmlspecialchars((string) ($agente['personalidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
          </div>
        </div>
      </div>

      <div>
        <label for="ag_instrucciones" class="block text-base font-semibold text-slate-300 mb-2">Instrucciones adicionales</label>
        <p class="text-sm text-slate-500 mb-2">Texto libre para esta empresa — no puede anular las reglas de seguridad del motor, esas están fijas en código.</p>
        <textarea id="ag_instrucciones" name="instrucciones" rows="4" placeholder="Ej.: en Arauca, si el cliente pide un taxi al corregimiento de X, aclara que el recargo es Y." class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base"><?= htmlspecialchars((string) ($agente['instrucciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div>
        <h3 class="text-base font-semibold text-slate-300 mb-3">Mensajes</h3>
        <div class="space-y-3">
          <div>
            <label for="ag_saludo" class="block text-sm text-slate-400 mb-2">Saludo inicial</label>
            <textarea id="ag_saludo" name="saludo_inicial" rows="2" placeholder="¡Hola! Soy el asistente de Radio Tax, ¿en qué te ayudo?" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base"><?= htmlspecialchars((string) ($agente['saludo_inicial'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div>
            <label for="ag_fuera_horario" class="block text-sm text-slate-400 mb-2">Fuera de horario</label>
            <textarea id="ag_fuera_horario" name="mensaje_fuera_horario" rows="2" placeholder="En este momento estamos cerrados. Escríbenos dentro de nuestro horario y con gusto te atendemos." class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base"><?= htmlspecialchars((string) ($agente['mensaje_fuera_horario'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div>
            <label for="ag_error" class="block text-sm text-slate-400 mb-2">Error / no puede continuar</label>
            <textarea id="ag_error" name="mensaje_error" rows="2" placeholder="En este momento no puedo completar la operación. Voy a pasar tu solicitud a una persona del equipo." class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base"><?= htmlspecialchars((string) ($agente['mensaje_error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
        </div>
      </div>

      <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Guardar</button>
    </form>

    <section class="bg-card border border-border rounded-xl p-6">
      <h2 class="font-semibold text-lg mb-2">Dónde opera</h2>
      <p class="text-sm text-slate-400 mb-4">País, departamento y ciudad donde presta servicio esta empresa.</p>
      <form method="post" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="guardar_ubicacion">
        <div>
          <label for="ub_pais" class="block text-sm text-slate-400 mb-2">País</label>
          <select id="ub_pais" name="pais" class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-44">
            <option value="">Selecciona…</option>
            <?php foreach (array_keys($departamentosPorPais) as $nombrePais): ?>
              <option value="<?= htmlspecialchars($nombrePais, ENT_QUOTES, 'UTF-8') ?>" <?= ($empresaUbicacion['pais'] ?? '') === $nombrePais ? 'selected' : '' ?>><?= htmlspecialchars($nombrePais, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="ub_departamento" class="block text-sm text-slate-400 mb-2">Departamento / Estado</label>
          <select id="ub_departamento" name="departamento" class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-52">
            <option value="">Selecciona un país primero…</option>
          </select>
        </div>
        <div>
          <label for="ub_ciudad" class="block text-sm text-slate-400 mb-2">Ciudad</label>
          <input id="ub_ciudad" name="ciudad" required value="<?= htmlspecialchars((string) ($empresaUbicacion['ciudad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-48">
        </div>
        <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Guardar</button>
      </form>
    </section>
  </main>

  <script>
  (() => {
    const departamentosPorPais = <?= json_encode($departamentosPorPais, JSON_UNESCAPED_UNICODE) ?>;
    const departamentoGuardado = <?= json_encode((string) ($empresaUbicacion['departamento'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    const selPais = document.getElementById('ub_pais');
    const selDepartamento = document.getElementById('ub_departamento');

    function pintarDepartamentos(paisElegido, departamentoAMarcar) {
      const lista = departamentosPorPais[paisElegido] || [];
      selDepartamento.innerHTML = '';
      if (lista.length === 0) {
        selDepartamento.appendChild(new Option('Selecciona un país primero…', ''));
        return;
      }
      selDepartamento.appendChild(new Option('Selecciona…', ''));
      for (const d of lista) {
        const opt = new Option(d, d);
        if (d === departamentoAMarcar) opt.selected = true;
        selDepartamento.appendChild(opt);
      }
    }

    selPais.addEventListener('change', () => pintarDepartamentos(selPais.value, ''));
    pintarDepartamentos(selPais.value, departamentoGuardado);
  })();
  </script>
</body>
</html>
