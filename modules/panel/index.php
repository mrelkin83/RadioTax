<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;

$usuario = Auth::requerirSesionDeEmpresa();
$csrf = Auth::tokenCsrf();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<meta name="usuario-id" content="<?= (int) $usuario['id'] ?>">
<meta name="usuario-rol" content="<?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?>">
<title>Centro de transmisión · <?= htmlspecialchars($usuario['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <header class="border-b border-border px-6 py-4 flex items-center justify-between bg-card/40 backdrop-blur">
    <div class="flex items-center gap-2.5">
      <svg class="w-5 h-5 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M8 17h8m-9-4h10l1.5-4.5a1 1 0 0 0-.95-1.32H6.45a1 1 0 0 0-.95 1.32L7 13Z"/>
        <circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/>
      </svg>
      <h1 class="text-base font-semibold">Centro de transmisión <span class="text-slate-500 font-normal">· <?= htmlspecialchars($usuario['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></h1>
    </div>
    <div class="flex items-center gap-5 text-sm text-slate-400">
      <span><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?> <span class="text-slate-600">·</span> <?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php if ($usuario['rol'] === 'ADMIN'): ?>
        <a href="/modules/admin/vehiculos.php" class="hover:text-foreground transition-colors">Administración</a>
      <?php endif; ?>
      <a href="/modules/panel/logout.php" class="text-red-400 hover:text-red-300 transition-colors">Salir</a>
    </div>
  </header>

  <main class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-[1600px] mx-auto">
    <section class="lg:col-span-2 space-y-8">
      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-sm text-slate-300 uppercase tracking-wide">Cola de solicitudes</h2>
          <button id="btn-nueva-solicitud" type="button" class="inline-flex items-center gap-1.5 text-sm font-medium bg-accent hover:bg-accent-hover text-on-accent px-3.5 py-2 rounded-lg">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Solicitud manual
          </button>
        </div>
        <div id="cola" class="space-y-3"></div>
      </div>

      <div>
        <h2 class="font-semibold text-sm text-slate-300 uppercase tracking-wide mb-3">Finalizadas hoy</h2>
        <div id="finalizadas" class="space-y-2"></div>
      </div>
    </section>

    <aside class="space-y-3">
      <h2 class="font-semibold text-sm text-slate-300 uppercase tracking-wide">Tablero de flota</h2>
      <div id="flota" class="space-y-2"></div>

      <h2 class="font-semibold text-sm text-slate-300 uppercase tracking-wide pt-5">Necesitan atención</h2>
      <p class="text-xs text-slate-500 -mt-1">Transferidas por el agente de IA o pausadas manualmente.</p>
      <div id="conversaciones" class="space-y-2"></div>
    </aside>
  </main>

  <dialog id="modal-solicitud" class="rounded-xl p-0 backdrop:bg-black/70 bg-transparent">
    <form id="form-solicitud" class="bg-card border border-border rounded-xl p-6 w-96 space-y-3 shadow-2xl">
      <h3 class="text-foreground font-semibold">Nueva solicitud manual</h3>
      <p class="text-xs text-slate-500 -mt-1.5">Úsala cuando el cliente llama por teléfono en vez de escribir por WhatsApp.</p>
      <input name="cliente_whatsapp" placeholder="WhatsApp del cliente" required class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm">
      <input name="cliente_nombre" placeholder="Nombre (opcional)" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm">
      <input name="tipo_servicio" placeholder="Tipo de servicio (ej. TAXI)" required value="TAXI" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm">
      <input name="recogida_texto" placeholder="Dirección de recogida" required class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm">
      <input name="destino_texto" placeholder="Destino" required class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm">
      <textarea name="observaciones" placeholder="Observaciones" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm"></textarea>
      <p id="error-solicitud" class="text-red-400 text-xs hidden" role="alert"></p>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="btn-cancelar-solicitud" class="px-3.5 py-2 text-sm text-slate-400 hover:text-slate-200 rounded-lg">Cancelar</button>
        <button type="submit" class="px-3.5 py-2 text-sm font-medium bg-accent hover:bg-accent-hover rounded-lg text-on-accent">Crear</button>
      </div>
    </form>
  </dialog>

  <script src="/modules/panel/assets/panel.js"></script>
</body>
</html>
