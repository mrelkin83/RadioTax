<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;

if (Auth::usuarioActual() !== null) {
    header('Location: /modules/panel/index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    if ($usuario === '' || $clave === '') {
        $error = 'Usuario y clave son obligatorios.';
    } elseif (Auth::intentarLogin($usuario, $clave) !== null) {
        header('Location: /modules/panel/index.php');
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
<title>Radio Tax · Centro de transmisión</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center">
  <form method="post" class="bg-slate-800 p-8 rounded-lg shadow-xl w-full max-w-sm">
    <h1 class="text-white text-xl font-semibold mb-6">Centro de transmisión</h1>
    <?php if ($error !== null): ?>
      <p class="bg-red-900/50 text-red-200 text-sm rounded p-3 mb-4"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <label class="block text-slate-300 text-sm mb-1" for="usuario">Usuario</label>
    <input id="usuario" name="usuario" type="text" required autofocus
           class="w-full mb-4 rounded bg-slate-700 text-white px-3 py-2 outline-none focus:ring-2 focus:ring-sky-500">
    <label class="block text-slate-300 text-sm mb-1" for="clave">Clave</label>
    <input id="clave" name="clave" type="password" required
           class="w-full mb-6 rounded bg-slate-700 text-white px-3 py-2 outline-none focus:ring-2 focus:ring-sky-500">
    <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white font-medium rounded py-2">
      Entrar
    </button>
  </form>
</body>
</html>
