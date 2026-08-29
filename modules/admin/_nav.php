<?php
// Se incluye desde dentro de las páginas admin; espera $activo ('vehiculos'|'conductores').
$tabs = ['vehiculos' => 'Vehículos', 'conductores' => 'Conductores', 'whatsapp' => 'WhatsApp', 'reportes' => 'Reportes'];
?>
<header class="border-b border-slate-800 px-6 py-4 flex items-center justify-between">
  <div class="flex items-center gap-6">
    <h1 class="text-lg font-semibold">Administración · Radio Tax</h1>
    <nav class="flex gap-4 text-sm">
      <?php foreach ($tabs as $clave => $etiqueta): ?>
        <a href="/modules/admin/<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>.php"
           class="<?= $activo === $clave ? 'text-white font-medium' : 'text-slate-400 hover:text-slate-200' ?>">
          <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="flex items-center gap-4 text-sm text-slate-400">
    <a href="/modules/panel/index.php" class="hover:text-slate-200">← Centro de transmisión</a>
    <a href="/modules/panel/logout.php" class="text-red-400 hover:text-red-300">Salir</a>
  </div>
</header>
