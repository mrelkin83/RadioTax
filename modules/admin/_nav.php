<?php
// Se incluye desde dentro de las páginas admin; espera $activo ('vehiculos'|'conductores'|...).
$tabs = ['vehiculos' => 'Vehículos', 'conductores' => 'Conductores', 'whatsapp' => 'WhatsApp', 'agente' => 'Agente IA', 'reportes' => 'Reportes'];
?>
<header class="border-b border-border px-6 py-4 flex items-center justify-between bg-card/40 backdrop-blur">
  <div class="flex items-center gap-8">
    <h1 class="text-base font-semibold">Administración <span class="text-slate-500 font-normal">· <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></h1>
    <nav class="flex gap-5 text-sm">
      <?php foreach ($tabs as $clave => $etiqueta): ?>
        <a href="/modules/admin/<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>.php"
           class="<?= $activo === $clave ? 'text-accent font-medium' : 'text-slate-400 hover:text-slate-200 transition-colors' ?>">
          <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="flex items-center gap-5 text-sm text-slate-400">
    <a href="/modules/panel/index.php" class="hover:text-foreground transition-colors">← Centro de transmisión</a>
    <a href="/modules/panel/logout.php" class="text-red-400 hover:text-red-300 transition-colors">Salir</a>
  </div>
</header>
