<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;

$usuario = Auth::requerirSesion();
$csrf = Auth::tokenCsrf();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<title>Centro de transmisión · Radio Tax</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
  <header class="border-b border-slate-800 px-6 py-4 flex items-center justify-between">
    <h1 class="text-lg font-semibold">Centro de transmisión</h1>
    <div class="flex items-center gap-4 text-sm text-slate-400">
      <span><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php if ($usuario['rol'] === 'ADMIN'): ?>
        <a href="/modules/admin/vehiculos.php" class="hover:text-slate-200">Administración</a>
      <?php endif; ?>
      <a href="/modules/panel/logout.php" class="text-red-400 hover:text-red-300">Salir</a>
    </div>
  </header>

  <main class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <section class="lg:col-span-2 space-y-6">
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-slate-200">Cola de solicitudes</h2>
          <button id="btn-nueva-solicitud" type="button" class="text-sm bg-sky-600 hover:bg-sky-500 px-3 py-1.5 rounded">+ Solicitud manual</button>
        </div>
        <div id="cola" class="space-y-3"></div>
      </div>

      <div>
        <h2 class="font-semibold text-slate-200 mb-3">Finalizadas hoy</h2>
        <div id="finalizadas" class="space-y-2"></div>
      </div>
    </section>

    <aside class="space-y-3">
      <h2 class="font-semibold text-slate-200">Tablero de flota</h2>
      <div id="flota" class="space-y-2"></div>
    </aside>
  </main>

  <dialog id="modal-solicitud" class="rounded-lg p-0 backdrop:bg-black/60">
    <form id="form-solicitud" class="bg-slate-800 p-6 w-96 space-y-3">
      <h3 class="text-white font-semibold">Nueva solicitud manual</h3>
      <p class="text-xs text-slate-400">Úsala cuando el cliente llama por teléfono en vez de escribir por WhatsApp.</p>
      <input name="cliente_whatsapp" placeholder="WhatsApp del cliente" required class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm">
      <input name="cliente_nombre" placeholder="Nombre (opcional)" class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm">
      <input name="tipo_servicio" placeholder="Tipo de servicio (ej. TAXI)" required value="TAXI" class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm">
      <input name="recogida_texto" placeholder="Dirección de recogida" required class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm">
      <input name="destino_texto" placeholder="Destino" required class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm">
      <textarea name="observaciones" placeholder="Observaciones" class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm"></textarea>
      <p id="error-solicitud" class="text-red-400 text-xs hidden"></p>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="btn-cancelar-solicitud" class="px-3 py-1.5 text-slate-300">Cancelar</button>
        <button type="submit" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 rounded text-white">Crear</button>
      </div>
    </form>
  </dialog>

  <script src="/modules/panel/assets/panel.js"></script>
</body>
</html>
