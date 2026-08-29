<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];
$error = null;

$estadosVehiculo = ['DISPONIBLE', 'SOLICITADO', 'EN_SERVICIO', 'EN_TURNO', 'FUERA_DE_TURNO', 'NO_DISPONIBLE', 'PENDIENTE_CONFIRMACION'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    $numeroInterno = trim((string) ($_POST['numero_interno'] ?? ''));
    $placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
    $tipo = trim((string) ($_POST['tipo'] ?? 'TAXI'));

    if ($accion === 'crear' || $accion === 'editar') {
        if ($numeroInterno === '' || $placa === '' || $tipo === '') {
            $error = 'Número interno, placa y tipo son obligatorios.';
        } elseif ($accion === 'crear') {
            try {
                $pdo->prepare(
                    'INSERT INTO tx_vehiculos (empresa_id, numero_interno, placa, tipo, estado_vehiculo)
                     VALUES (:empresa, :numero, :placa, :tipo, "FUERA_DE_TURNO")'
                )->execute(['empresa' => $empresaId, 'numero' => $numeroInterno, 'placa' => $placa, 'tipo' => $tipo]);
                header('Location: /modules/admin/vehiculos.php');
                exit;
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un vehículo con esa placa en esta empresa.' : 'No se pudo crear el vehículo.';
            }
        } elseif ($accion === 'editar') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare(
                        'UPDATE tx_vehiculos SET numero_interno = :numero, placa = :placa, tipo = :tipo
                         WHERE id = :id AND empresa_id = :empresa'
                    )->execute(['numero' => $numeroInterno, 'placa' => $placa, 'tipo' => $tipo, 'id' => $id, 'empresa' => $empresaId]);
                    header('Location: /modules/admin/vehiculos.php');
                    exit;
                } catch (\PDOException $e) {
                    $error = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un vehículo con esa placa en esta empresa.' : 'No se pudo actualizar el vehículo.';
                }
            }
        }
    } elseif ($accion === 'abrir_turno') {
        $conductorId = (int) ($_POST['conductor_id'] ?? 0);
        $vehiculoId = (int) ($_POST['vehiculo_id'] ?? 0);
        $sentencia = $pdo->prepare('SELECT id FROM tx_conductores WHERE id = :id AND empresa_id = :empresa LIMIT 1');
        $sentencia->execute(['id' => $conductorId, 'empresa' => $empresaId]);
        $sentenciaV = $pdo->prepare('SELECT id FROM tx_vehiculos WHERE id = :id AND empresa_id = :empresa LIMIT 1');
        $sentenciaV->execute(['id' => $vehiculoId, 'empresa' => $empresaId]);
        if ($conductorId <= 0 || $vehiculoId <= 0 || $sentencia->fetchColumn() === false || $sentenciaV->fetchColumn() === false) {
            $error = 'Conductor o vehículo inválido.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'UPDATE tx_vehiculo_conductor SET fecha_hasta = NOW()
                     WHERE fecha_hasta IS NULL AND (vehiculo_id = :vehiculo OR conductor_id = :conductor)'
                )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId]);
                $pdo->prepare(
                    'INSERT INTO tx_vehiculo_conductor (vehiculo_id, conductor_id, fecha_desde) VALUES (:vehiculo, :conductor, NOW())'
                )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId]);
                $pdo->prepare(
                    "INSERT INTO tx_turnos (conductor_id, vehiculo_id, inicio, abierto_por) VALUES (:conductor, :vehiculo, NOW(), 'OPERADOR')"
                )->execute(['conductor' => $conductorId, 'vehiculo' => $vehiculoId]);
                $pdo->prepare("UPDATE tx_vehiculos SET estado_vehiculo = 'DISPONIBLE' WHERE id = :id")->execute(['id' => $vehiculoId]);
                $pdo->commit();
                header('Location: /modules/admin/vehiculos.php');
                exit;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $error = 'No se pudo abrir el turno.';
            }
        }
    } elseif ($accion === 'cerrar_turno') {
        $turnoId = (int) ($_POST['turno_id'] ?? 0);
        $sentencia = $pdo->prepare(
            'SELECT t.id, t.vehiculo_id FROM tx_turnos t
             INNER JOIN tx_vehiculos v ON v.id = t.vehiculo_id
             WHERE t.id = :id AND v.empresa_id = :empresa AND t.fin IS NULL LIMIT 1'
        );
        $sentencia->execute(['id' => $turnoId, 'empresa' => $empresaId]);
        $turno = $sentencia->fetch();
        if ($turno === false) {
            $error = 'Turno no encontrado o ya cerrado.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE tx_turnos SET fin = NOW() WHERE id = :id')->execute(['id' => $turnoId]);
                $pdo->prepare("UPDATE tx_vehiculos SET estado_vehiculo = 'FUERA_DE_TURNO' WHERE id = :id")
                    ->execute(['id' => $turno['vehiculo_id']]);
                $pdo->commit();
                header('Location: /modules/admin/vehiculos.php');
                exit;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $error = 'No se pudo cerrar el turno.';
            }
        }
    }
}

$vehiculos = $pdo->prepare(
    "SELECT v.*, cond.id AS conductor_id, cond.nombre AS conductor_nombre, t.id AS turno_id, t.inicio AS turno_inicio
     FROM tx_vehiculos v
     LEFT JOIN tx_vehiculo_conductor vc ON vc.vehiculo_id = v.id AND vc.fecha_hasta IS NULL
     LEFT JOIN tx_conductores cond ON cond.id = vc.conductor_id
     LEFT JOIN tx_turnos t ON t.vehiculo_id = v.id AND t.fin IS NULL
     WHERE v.empresa_id = :empresa
     ORDER BY v.numero_interno ASC"
);
$vehiculos->execute(['empresa' => $empresaId]);
$vehiculos = $vehiculos->fetchAll();

$conductoresActivos = $pdo->prepare("SELECT id, nombre FROM tx_conductores WHERE empresa_id = :empresa AND estado = 'ACTIVO' ORDER BY nombre ASC");
$conductoresActivos->execute(['empresa' => $empresaId]);
$conductoresActivos = $conductoresActivos->fetchAll();

$csrf = Auth::tokenCsrf();
$activo = 'vehiculos';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vehículos · Administración · <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-5xl mx-auto space-y-8">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-base rounded-lg px-4 py-3" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <section class="bg-card border border-border rounded-xl p-6">
      <h2 class="font-semibold text-lg mb-4">Nuevo vehículo</h2>
      <form method="post" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="crear">
        <div>
          <label for="v_crear_numero" class="block text-sm text-slate-400 mb-2">Número interno</label>
          <input id="v_crear_numero" name="numero_interno" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-36">
        </div>
        <div>
          <label for="v_crear_placa" class="block text-sm text-slate-400 mb-2">Placa</label>
          <input id="v_crear_placa" name="placa" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-36 font-mono">
        </div>
        <div>
          <label for="v_crear_tipo" class="block text-sm text-slate-400 mb-2">Tipo de servicio</label>
          <input id="v_crear_tipo" name="tipo" value="TAXI" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-40">
        </div>
        <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Crear</button>
      </form>
    </section>

    <section class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-base text-neutral-800 uppercase tracking-wide">Flota (<?= count($vehiculos) ?>)</h2>
        <input type="search" id="buscador-vehiculos" placeholder="Buscar por número, placa, tipo o conductor…" class="rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2 text-sm w-full sm:w-72">
      </div>
      <?php if ($vehiculos === []): ?>
        <p class="text-neutral-700 text-base">Todavía no hay vehículos registrados.</p>
      <?php endif; ?>
      <p id="vehiculos-sin-resultados" class="text-neutral-700 text-base hidden">Ningún vehículo coincide con la búsqueda.</p>
      <?php foreach ($vehiculos as $v): ?>
        <div class="vehiculo-fila bg-card border border-border rounded-xl p-5 space-y-4" data-buscar="<?= htmlspecialchars(mb_strtolower($v['numero_interno'] . ' ' . $v['placa'] . ' ' . $v['tipo'] . ' ' . (string) ($v['conductor_nombre'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
          <form method="post" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <div>
              <label for="v_<?= (int) $v['id'] ?>_numero" class="block text-sm text-slate-400 mb-2">Número interno</label>
              <input id="v_<?= (int) $v['id'] ?>_numero" name="numero_interno" value="<?= htmlspecialchars($v['numero_interno'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-32">
            </div>
            <div>
              <label for="v_<?= (int) $v['id'] ?>_placa" class="block text-sm text-slate-400 mb-2">Placa</label>
              <input id="v_<?= (int) $v['id'] ?>_placa" name="placa" value="<?= htmlspecialchars($v['placa'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-32 font-mono">
            </div>
            <div>
              <label for="v_<?= (int) $v['id'] ?>_tipo" class="block text-sm text-slate-400 mb-2">Tipo</label>
              <input id="v_<?= (int) $v['id'] ?>_tipo" name="tipo" value="<?= htmlspecialchars($v['tipo'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-36">
            </div>
            <div class="text-sm text-slate-400 px-2 py-2.5">
              Estado: <span class="text-slate-200"><?= htmlspecialchars($v['estado_vehiculo'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="submit" class="bg-muted hover:bg-secondary text-slate-100 text-base font-medium rounded-lg px-4 py-2.5">Guardar</button>
          </form>

          <div class="pt-3 border-t border-border flex flex-wrap items-center gap-3">
            <span class="text-sm text-slate-400 w-full sm:w-auto">
              <?= $v['conductor_nombre'] ? 'Conductor: ' . htmlspecialchars((string) $v['conductor_nombre'], ENT_QUOTES, 'UTF-8') : 'Sin conductor asignado' ?>
            </span>
            <?php if ($v['turno_id']): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accion" value="cerrar_turno">
                <input type="hidden" name="turno_id" value="<?= (int) $v['turno_id'] ?>">
                <button type="submit" class="bg-muted hover:bg-secondary text-slate-100 text-sm font-medium rounded-lg px-4 py-2">Cerrar turno</button>
              </form>
            <?php else: ?>
              <form method="post" class="flex items-center gap-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accion" value="abrir_turno">
                <input type="hidden" name="vehiculo_id" value="<?= (int) $v['id'] ?>">
                <label for="v_<?= (int) $v['id'] ?>_conductor" class="sr-only">Conductor para abrir turno</label>
                <select id="v_<?= (int) $v['id'] ?>_conductor" name="conductor_id" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm">
                  <option value="">Conductor…</option>
                  <?php foreach ($conductoresActivos as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars((string) $c['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-sm font-medium rounded-lg px-4 py-2">Abrir turno</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </main>

  <script>
  (() => {
    const buscador = document.getElementById('buscador-vehiculos');
    const filas = document.querySelectorAll('.vehiculo-fila');
    const sinResultados = document.getElementById('vehiculos-sin-resultados');
    if (!buscador) return;
    buscador.addEventListener('input', () => {
      const consulta = buscador.value.trim().toLowerCase();
      let visibles = 0;
      filas.forEach((fila) => {
        const coincide = fila.dataset.buscar.includes(consulta);
        fila.classList.toggle('hidden', !coincide);
        if (coincide) visibles++;
      });
      if (sinResultados) sinResultados.classList.toggle('hidden', visibles !== 0 || filas.length === 0);
    });
  })();
  </script>
</body>
</html>
