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
}

$vehiculos = $pdo->prepare('SELECT * FROM tx_vehiculos WHERE empresa_id = :empresa ORDER BY numero_interno ASC');
$vehiculos->execute(['empresa' => $empresaId]);
$vehiculos = $vehiculos->fetchAll();
$csrf = Auth::tokenCsrf();
$activo = 'vehiculos';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vehículos · Administración · Radio Tax</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-3xl mx-auto space-y-6">
    <?php if ($error !== null): ?>
      <p class="bg-red-900/50 text-red-200 text-sm rounded p-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <section class="bg-slate-800 rounded-lg p-4">
      <h2 class="font-semibold mb-3">Nuevo vehículo</h2>
      <form method="post" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="crear">
        <div>
          <label for="v_crear_numero" class="block text-xs text-slate-400 mb-1">Número interno</label>
          <input id="v_crear_numero" name="numero_interno" required class="rounded bg-slate-700 text-white px-3 py-2 text-sm w-28">
        </div>
        <div>
          <label for="v_crear_placa" class="block text-xs text-slate-400 mb-1">Placa</label>
          <input id="v_crear_placa" name="placa" required class="rounded bg-slate-700 text-white px-3 py-2 text-sm w-28">
        </div>
        <div>
          <label for="v_crear_tipo" class="block text-xs text-slate-400 mb-1">Tipo de servicio</label>
          <input id="v_crear_tipo" name="tipo" value="TAXI" required class="rounded bg-slate-700 text-white px-3 py-2 text-sm w-32">
        </div>
        <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium rounded px-4 py-2">Crear</button>
      </form>
    </section>

    <section class="space-y-2">
      <h2 class="font-semibold text-slate-200">Flota (<?= count($vehiculos) ?>)</h2>
      <?php if ($vehiculos === []): ?>
        <p class="text-slate-500 text-sm">Todavía no hay vehículos registrados.</p>
      <?php endif; ?>
      <?php foreach ($vehiculos as $v): ?>
        <form method="post" class="bg-slate-800 rounded-lg p-3 flex flex-wrap gap-2 items-end">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
          <div>
            <label for="v_<?= (int) $v['id'] ?>_numero" class="block text-xs text-slate-400 mb-1">Número interno</label>
            <input id="v_<?= (int) $v['id'] ?>_numero" name="numero_interno" value="<?= htmlspecialchars($v['numero_interno'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded bg-slate-700 text-white px-3 py-2 text-sm w-24">
          </div>
          <div>
            <label for="v_<?= (int) $v['id'] ?>_placa" class="block text-xs text-slate-400 mb-1">Placa</label>
            <input id="v_<?= (int) $v['id'] ?>_placa" name="placa" value="<?= htmlspecialchars($v['placa'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded bg-slate-700 text-white px-3 py-2 text-sm w-24">
          </div>
          <div>
            <label for="v_<?= (int) $v['id'] ?>_tipo" class="block text-xs text-slate-400 mb-1">Tipo</label>
            <input id="v_<?= (int) $v['id'] ?>_tipo" name="tipo" value="<?= htmlspecialchars($v['tipo'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded bg-slate-700 text-white px-3 py-2 text-sm w-28">
          </div>
          <div class="text-xs text-slate-400 px-2 py-2">
            Estado actual: <span class="text-slate-200"><?= htmlspecialchars($v['estado_vehiculo'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-slate-600">(se cambia desde el Centro de transmisión)</span>
          </div>
          <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white text-sm rounded px-3 py-2">Guardar</button>
        </form>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
