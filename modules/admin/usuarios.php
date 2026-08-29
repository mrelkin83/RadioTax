<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];
$error = null;
$credencialesNuevas = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear_usuario') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $rol = (string) ($_POST['rol'] ?? 'RADIOOPERADOR');
        $rol = in_array($rol, ['RADIOOPERADOR', 'ADMIN'], true) ? $rol : 'RADIOOPERADOR';

        if ($nombre === '' || $usuario === '') {
            $error = 'Nombre y usuario son obligatorios.';
        } else {
            $sentencia = $pdo->prepare('SELECT id FROM tx_usuarios WHERE usuario = :usuario LIMIT 1');
            $sentencia->execute(['usuario' => $usuario]);
            if ($sentencia->fetchColumn() !== false) {
                $error = "El usuario \"{$usuario}\" ya existe — elegí otro nombre de usuario.";
            } else {
                $clave = bin2hex(random_bytes(6));
                $pdo->prepare(
                    'INSERT INTO tx_usuarios (empresa_id, nombre, usuario, clave_hash, rol) VALUES (:empresa, :nombre, :usuario, :hash, :rol)'
                )->execute([
                    'empresa' => $empresaId,
                    'nombre' => $nombre,
                    'usuario' => $usuario,
                    'hash' => password_hash($clave, PASSWORD_DEFAULT),
                    'rol' => $rol,
                ]);
                $credencialesNuevas = ['usuario' => $usuario, 'clave' => $clave, 'titulo' => 'Usuario creado'];
            }
        }
    }

    if ($accion === 'restablecer_clave') {
        $id = (int) ($_POST['id'] ?? 0);
        $sentencia = $pdo->prepare('SELECT usuario FROM tx_usuarios WHERE id = :id AND empresa_id = :empresa LIMIT 1');
        $sentencia->execute(['id' => $id, 'empresa' => $empresaId]);
        $usuarioObjetivo = $sentencia->fetchColumn();
        if ($usuarioObjetivo === false) {
            $error = 'Usuario no encontrado.';
        } else {
            $clave = bin2hex(random_bytes(6));
            $pdo->prepare('UPDATE tx_usuarios SET clave_hash = :hash WHERE id = :id AND empresa_id = :empresa')
                ->execute(['hash' => password_hash($clave, PASSWORD_DEFAULT), 'id' => $id, 'empresa' => $empresaId]);
            $credencialesNuevas = ['usuario' => (string) $usuarioObjetivo, 'clave' => $clave, 'titulo' => 'Clave restablecida'];
        }
    }

    if ($accion === 'cambiar_estado') {
        $id = (int) ($_POST['id'] ?? 0);
        $nuevoEstado = (int) ($_POST['nuevo_estado'] ?? 1);
        if ($id === (int) $usuarioActual['id']) {
            $error = 'No podés desactivar tu propia cuenta.';
        } else {
            $pdo->prepare('UPDATE tx_usuarios SET activo = :activo WHERE id = :id AND empresa_id = :empresa')
                ->execute(['activo' => $nuevoEstado ? 1 : 0, 'id' => $id, 'empresa' => $empresaId]);
            header('Location: /modules/admin/usuarios.php');
            exit;
        }
    }
}

$usuarios = $pdo->prepare('SELECT id, nombre, usuario, rol, activo, creado_en FROM tx_usuarios WHERE empresa_id = :empresa ORDER BY creado_en ASC');
$usuarios->execute(['empresa' => $empresaId]);
$usuarios = $usuarios->fetchAll();
$csrf = Auth::tokenCsrf();
$activo = 'usuarios';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Usuarios · Administración · <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-4xl mx-auto space-y-6">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-base rounded-lg px-4 py-3" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($credencialesNuevas !== null): ?>
      <div class="bg-warning/10 border border-warning/30 text-amber-200 text-base rounded-xl p-6 space-y-2">
        <p class="font-medium"><?= htmlspecialchars($credencialesNuevas['titulo'], ENT_QUOTES, 'UTF-8') ?> — guardá esto ahora, no se vuelve a mostrar:</p>
        <p>Usuario: <code class="bg-muted border border-border rounded px-2 py-1 font-mono"><?= htmlspecialchars($credencialesNuevas['usuario'], ENT_QUOTES, 'UTF-8') ?></code></p>
        <p>Clave: <code class="bg-muted border border-border rounded px-2 py-1 font-mono"><?= htmlspecialchars($credencialesNuevas['clave'], ENT_QUOTES, 'UTF-8') ?></code></p>
      </div>
    <?php endif; ?>

    <section class="bg-card border border-border rounded-xl p-6">
      <h2 class="font-semibold text-lg mb-4">Nuevo usuario</h2>
      <form method="post" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="crear_usuario">
        <div>
          <label for="nu_nombre" class="block text-sm text-slate-400 mb-2">Nombre</label>
          <input id="nu_nombre" name="nombre" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-48">
        </div>
        <div>
          <label for="nu_usuario" class="block text-sm text-slate-400 mb-2">Usuario (login)</label>
          <input id="nu_usuario" name="usuario" required class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base w-40 font-mono">
        </div>
        <div>
          <label for="nu_rol" class="block text-sm text-slate-400 mb-2">Rol</label>
          <select id="nu_rol" name="rol" class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base">
            <option value="RADIOOPERADOR">Radiooperador</option>
            <option value="ADMIN">Administrador</option>
          </select>
        </div>
        <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Crear</button>
      </form>
      <p class="text-sm text-slate-500 mt-3">La clave se genera sola y se muestra una única vez — no hace falta escribirla acá.</p>
    </section>

    <section class="space-y-4">
      <h2 class="font-semibold text-base text-neutral-800 uppercase tracking-wide">Usuarios (<?= count($usuarios) ?>)</h2>
      <?php foreach ($usuarios as $u): ?>
        <div class="bg-card border border-border rounded-xl p-5 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-foreground font-medium">
              <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
              <span class="text-slate-500 text-sm">· <span class="font-mono"><?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?></span> · <?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ((int) $u['id'] === (int) $usuarioActual['id']): ?>
                <span class="text-xs text-slate-500">(vos)</span>
              <?php endif; ?>
            </p>
            <p class="text-xs mt-1">
              <?php if ((int) $u['activo'] === 1): ?>
                <span class="inline-flex items-center gap-1.5 text-emerald-400"><span class="punto-estado" style="background:#22C55E"></span>Activo</span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1.5 text-slate-500"><span class="punto-estado" style="background:#64748B"></span>Desactivado</span>
              <?php endif; ?>
            </p>
          </div>
          <div class="flex gap-2">
            <?php if ((int) $u['id'] === (int) $usuarioActual['id']): ?>
              <a href="/modules/panel/cuenta.php" class="bg-warning/15 hover:bg-warning/25 text-amber-200 text-sm font-medium rounded-lg px-4 py-2">Cambiar mi contraseña</a>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accion" value="restablecer_clave">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="bg-warning/15 hover:bg-warning/25 text-amber-200 text-sm font-medium rounded-lg px-4 py-2">Restablecer clave</button>
              </form>
            <?php endif; ?>
            <?php if ((int) $u['id'] !== (int) $usuarioActual['id']): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accion" value="cambiar_estado">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="nuevo_estado" value="<?= (int) $u['activo'] === 1 ? 0 : 1 ?>">
                <?php if ((int) $u['activo'] === 1): ?>
                  <button type="submit" class="bg-destructive/15 hover:bg-destructive/25 text-red-300 text-sm font-medium rounded-lg px-4 py-2">Desactivar</button>
                <?php else: ?>
                  <button type="submit" class="bg-muted hover:bg-secondary text-slate-100 text-sm font-medium rounded-lg px-4 py-2">Activar</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
