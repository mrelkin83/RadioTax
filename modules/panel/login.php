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
      <span class="text-neutral-800 text-sm font-medium tracking-wide uppercase">Despacho</span>
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
      <div class="relative mb-3">
        <input id="clave" name="clave" type="password" required autocomplete="current-password"
               class="w-full rounded-lg bg-muted border border-border text-foreground px-3.5 py-2.5 pr-11 text-sm placeholder:text-slate-500">
        <button type="button" id="btn-mostrar-clave" aria-label="Mostrar clave" aria-pressed="false"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-200">
          <svg id="icono-mostrar" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          <svg id="icono-ocultar" class="w-4 h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19.4 19.4 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a19.4 19.4 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
            <path d="M1 1l22 22"/>
          </svg>
        </button>
      </div>

      <label class="flex items-center gap-2 text-slate-400 text-sm mb-6">
        <input type="checkbox" id="recordar" name="recordar" class="accent-accent w-4 h-4">
        Recordar usuario en este dispositivo
      </label>

      <button type="submit" class="w-full bg-accent hover:bg-accent-hover text-on-accent font-semibold text-sm rounded-lg py-2.5">
        Entrar
      </button>
    </form>
  </div>

  <script>
  (() => {
    const CLAVE_STORAGE = 'taxis_usuario_recordado';
    const inputUsuario = document.getElementById('usuario');
    const checkRecordar = document.getElementById('recordar');
    const inputClave = document.getElementById('clave');
    const btnMostrar = document.getElementById('btn-mostrar-clave');
    const iconoMostrar = document.getElementById('icono-mostrar');
    const iconoOcultar = document.getElementById('icono-ocultar');
    const form = inputUsuario.closest('form');

    try {
      const guardado = localStorage.getItem(CLAVE_STORAGE);
      if (guardado) {
        inputUsuario.value = guardado;
        checkRecordar.checked = true;
        inputClave.focus();
      }
    } catch (e) {
      // localStorage puede fallar (modo privado, etc.) — el login sigue funcionando igual.
    }

    form.addEventListener('submit', () => {
      try {
        if (checkRecordar.checked && inputUsuario.value.trim()) {
          localStorage.setItem(CLAVE_STORAGE, inputUsuario.value.trim());
        } else {
          localStorage.removeItem(CLAVE_STORAGE);
        }
      } catch (e) {
        // ignorar
      }
    });

    btnMostrar.addEventListener('click', () => {
      const mostrando = inputClave.type === 'text';
      inputClave.type = mostrando ? 'password' : 'text';
      btnMostrar.setAttribute('aria-pressed', String(!mostrando));
      btnMostrar.setAttribute('aria-label', mostrando ? 'Mostrar clave' : 'Ocultar clave');
      iconoMostrar.classList.toggle('hidden', !mostrando);
      iconoOcultar.classList.toggle('hidden', mostrando);
    });
  })();
  </script>
</body>
</html>
