<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;

function destinoSegunRol(array $usuario): string
{
    return $usuario['rol'] === 'SUPERADMIN' ? '/modules/plataforma/empresas.php' : '/modules/panel/index.php';
}

$actual = Auth::usuarioActual();
if ($actual !== null) {
    header('Location: ' . destinoSegunRol($actual));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    if ($usuario === '' || $clave === '') {
        $error = 'Usuario y clave son obligatorios.';
    } elseif (($fila = Auth::intentarLogin($usuario, $clave)) !== null) {
        header('Location: ' . destinoSegunRol(['rol' => $fila['rol']]));
        exit;
    } else {
        $error = 'Usuario o clave incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Centro de transmisión</title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-sm">
    <div class="flex items-center gap-2 mb-6 justify-center">
      <svg class="w-6 h-6 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M8 17h8m-9-4h10l1.5-4.5a1 1 0 0 0-.95-1.32H6.45a1 1 0 0 0-.95 1.32L7 13Z"/>
        <circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/>
      </svg>
      <span class="text-slate-400 text-sm font-medium tracking-wide uppercase">Despacho</span>
    </div>
    <form method="post" class="bg-card border border-border rounded-xl shadow-2xl p-8" novalidate>
      <h1 class="text-foreground text-xl font-semibold mb-6">Centro de transmisión</h1>
      <?php if ($error !== null): ?>
        <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-sm rounded-lg px-3 py-2 mb-4" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <label class="block text-slate-400 text-sm mb-1.5" for="usuario">Usuario</label>
      <input id="usuario" name="usuario" type="text" required autofocus autocomplete="username"
             class="w-full mb-4 rounded-lg bg-muted border border-border text-foreground px-3.5 py-2.5 text-sm placeholder:text-slate-500">
      <label class="block text-slate-400 text-sm mb-1.5" for="clave">Clave</label>
      <input id="clave" name="clave" type="password" required autocomplete="current-password"
             class="w-full mb-6 rounded-lg bg-muted border border-border text-foreground px-3.5 py-2.5 text-sm placeholder:text-slate-500">
      <button type="submit" class="w-full bg-accent hover:bg-accent-hover text-on-accent font-semibold text-sm rounded-lg py-2.5">
        Entrar
      </button>
    </form>
  </div>
</body>
</html>
