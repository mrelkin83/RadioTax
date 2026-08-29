<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $documento = trim((string) ($_POST['documento'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
    $estado = (string) ($_POST['estado'] ?? 'ACTIVO');
    $estado = in_array($estado, ['ACTIVO', 'INACTIVO'], true) ? $estado : 'ACTIVO';

    if ($nombre === '' || $documento === '' || $telefono === '') {
        $error = 'Nombre, documento y teléfono son obligatorios.';
    } elseif ($accion === 'crear') {
        try {
            $pdo->prepare(
                'INSERT INTO tx_conductores (empresa_id, nombre, documento, telefono, whatsapp, estado)
                 VALUES (:empresa, :nombre, :documento, :telefono, :whatsapp, :estado)'
            )->execute([
                'empresa' => $empresaId,
                'nombre' => $nombre,
                'documento' => $documento,
                'telefono' => $telefono,
                'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
                'estado' => $estado,
            ]);
            header('Location: /modules/admin/conductores.php');
            exit;
        } catch (\PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un conductor con ese documento en esta empresa.' : 'No se pudo crear el conductor.';
        }
    } elseif ($accion === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare(
                    'UPDATE tx_conductores SET nombre = :nombre, documento = :documento, telefono = :telefono, whatsapp = :whatsapp, estado = :estado
                     WHERE id = :id AND empresa_id = :empresa'
                )->execute([
                    'nombre' => $nombre,
                    'documento' => $documento,
                    'telefono' => $telefono,
                    'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
                    'estado' => $estado,
                    'id' => $id,
                    'empresa' => $empresaId,
                ]);
                header('Location: /modules/admin/conductores.php');
                exit;
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un conductor con ese documento en esta empresa.' : 'No se pudo actualizar el conductor.';
            }
        }
    }
}

$conductores = $pdo->prepare('SELECT * FROM tx_conductores WHERE empresa_id = :empresa ORDER BY nombre ASC');
$conductores->execute(['empresa' => $empresaId]);
$conductores = $conductores->fetchAll();
$csrf = Auth::tokenCsrf();
$activo = 'conductores';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Conductores · Administración · <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-3xl mx-auto space-y-6">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-sm rounded-lg px-3 py-2" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <section class="bg-card border border-border rounded-xl p-4">
      <h2 class="font-semibold mb-3">Nuevo conductor</h2>
      <form method="post" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="crear">
        <div>
          <label for="c_crear_nombre" class="block text-xs text-slate-400 mb-1.5">Nombre</label>
          <input id="c_crear_nombre" name="nombre" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-40">
        </div>
        <div>
          <label for="c_crear_documento" class="block text-xs text-slate-400 mb-1.5">Documento</label>
          <input id="c_crear_documento" name="documento" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-32 font-mono">
        </div>
        <div>
          <label for="c_crear_telefono" class="block text-xs text-slate-400 mb-1.5">Teléfono</label>
          <input id="c_crear_telefono" name="telefono" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-32 font-mono">
        </div>
        <div>
          <label for="c_crear_whatsapp" class="block text-xs text-slate-400 mb-1.5">WhatsApp (opcional)</label>
          <input id="c_crear_whatsapp" name="whatsapp" class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-32 font-mono">
        </div>
        <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-sm font-medium rounded-lg px-4 py-2">Crear</button>
      </form>
    </section>

    <section class="space-y-2">
      <h2 class="font-semibold text-sm text-slate-300 uppercase tracking-wide">Conductores (<?= count($conductores) ?>)</h2>
      <?php if ($conductores === []): ?>
        <p class="text-slate-500 text-sm">Todavía no hay conductores registrados.</p>
      <?php endif; ?>
      <?php foreach ($conductores as $c): ?>
        <form method="post" class="bg-card border border-border rounded-xl p-3 flex flex-wrap gap-2 items-end">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <div>
            <label for="c_<?= (int) $c['id'] ?>_nombre" class="block text-xs text-slate-400 mb-1.5">Nombre</label>
            <input id="c_<?= (int) $c['id'] ?>_nombre" name="nombre" value="<?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-36">
          </div>
          <div>
            <label for="c_<?= (int) $c['id'] ?>_documento" class="block text-xs text-slate-400 mb-1.5">Documento</label>
            <input id="c_<?= (int) $c['id'] ?>_documento" name="documento" value="<?= htmlspecialchars($c['documento'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-28 font-mono">
          </div>
          <div>
            <label for="c_<?= (int) $c['id'] ?>_telefono" class="block text-xs text-slate-400 mb-1.5">Teléfono</label>
            <input id="c_<?= (int) $c['id'] ?>_telefono" name="telefono" value="<?= htmlspecialchars($c['telefono'], ENT_QUOTES, 'UTF-8') ?>" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-28 font-mono">
          </div>
          <div>
            <label for="c_<?= (int) $c['id'] ?>_whatsapp" class="block text-xs text-slate-400 mb-1.5">WhatsApp</label>
            <input id="c_<?= (int) $c['id'] ?>_whatsapp" name="whatsapp" value="<?= htmlspecialchars((string) ($c['whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-28 font-mono">
          </div>
          <div>
            <label for="c_<?= (int) $c['id'] ?>_estado" class="block text-xs text-slate-400 mb-1.5">Estado</label>
            <select id="c_<?= (int) $c['id'] ?>_estado" name="estado" class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm">
              <option value="ACTIVO" <?= $c['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
              <option value="INACTIVO" <?= $c['estado'] === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
            </select>
          </div>
          <button type="submit" class="bg-muted hover:bg-secondary text-slate-100 text-sm font-medium rounded-lg px-3.5 py-2">Guardar</button>
        </form>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
