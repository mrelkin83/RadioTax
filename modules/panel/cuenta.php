<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

$usuario = Auth::requerirSesion();
$pdo = Database::conexion();
$error = null;
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claveActual = (string) ($_POST['clave_actual'] ?? '');
    $claveNueva = (string) ($_POST['clave_nueva'] ?? '');
    $claveNuevaConfirmar = (string) ($_POST['clave_nueva_confirmar'] ?? '');

    $sentencia = $pdo->prepare('SELECT clave_hash FROM tx_usuarios WHERE id = :id LIMIT 1');
    $sentencia->execute(['id' => $usuario['id']]);
    $fila = $sentencia->fetch();

    if ($fila === false || !password_verify($claveActual, $fila['clave_hash'])) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (mb_strlen($claveNueva) < 8) {
        $error = 'La contraseña nueva debe tener al menos 8 caracteres.';
    } elseif ($claveNueva !== $claveNuevaConfirmar) {
        $error = 'La confirmación no coincide con la contraseña nueva.';
    } else {
        $pdo->prepare('UPDATE tx_usuarios SET clave_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($claveNueva, PASSWORD_DEFAULT), 'id' => $usuario['id']]);
        $exito = true;
    }
}

$csrf = Auth::tokenCsrf();
$volverA = $usuario['rol'] === 'ADMIN' ? '/modules/admin/vehiculos.php' : '/modules/panel/index.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mi cuenta · <?= htmlspecialchars($usuario['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <header class="border-b border-border bg-card/90 backdrop-blur px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-3">
    <h1 class="text-base font-semibold truncate">Mi cuenta <span class="text-slate-500 font-normal">· <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></span></h1>
    <a href="<?= htmlspecialchars($volverA, ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-400 hover:text-foreground transition-colors">← Volver</a>
  </header>

  <main class="p-6 max-w-md mx-auto">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-sm rounded-lg px-4 py-3 mb-5" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif ($exito): ?>
      <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-sm rounded-lg px-4 py-3 mb-5">Contraseña actualizada.</p>
    <?php endif; ?>

    <form method="post" class="bg-card border border-border rounded-xl p-6 space-y-4">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <h2 class="font-semibold text-lg mb-1">Cambiar contraseña</h2>
      <p class="text-sm text-slate-400 mb-2">Necesitás tu contraseña actual para poder cambiarla.</p>

      <div>
        <label for="clave_actual" class="block text-sm text-slate-400 mb-2">Contraseña actual</label>
        <input id="clave_actual" name="clave_actual" type="password" required autocomplete="current-password" class="w-full rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base">
      </div>
      <div>
        <label for="clave_nueva" class="block text-sm text-slate-400 mb-2">Contraseña nueva</label>
        <input id="clave_nueva" name="clave_nueva" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base">
      </div>
      <div>
        <label for="clave_nueva_confirmar" class="block text-sm text-slate-400 mb-2">Confirmar contraseña nueva</label>
        <input id="clave_nueva_confirmar" name="clave_nueva_confirmar" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base">
      </div>

      <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Guardar</button>
    </form>
  </main>
</body>
</html>
