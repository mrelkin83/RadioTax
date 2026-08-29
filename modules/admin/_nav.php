<?php
// Se incluye desde dentro de las páginas admin; espera $activo ('vehiculos'|'conductores'|...).
// Reportes es la única sección que también ve el RADIOOPERADOR — el resto
// de las pestañas ni se muestran si no es ADMIN (esas páginas igual las
// bloquean del lado del servidor, pero no tiene sentido mostrar un enlace
// a algo a lo que no puede entrar).
$esAdmin = $usuarioActual['rol'] === 'ADMIN';
$tabs = $esAdmin
    ? ['vehiculos' => 'Vehículos', 'conductores' => 'Conductores', 'usuarios' => 'Usuarios', 'whatsapp' => 'WhatsApp', 'agente' => 'Agente IA', 'reportes' => 'Reportes']
    : ['reportes' => 'Reportes'];
?>
<header class="border-b border-border bg-card/90 backdrop-blur">
  <div class="px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-3">
    <h1 class="text-base font-semibold truncate"><?= $esAdmin ? 'Administración' : 'Reportes' ?> <span class="text-slate-500 font-normal">· <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></h1>
    <div class="flex items-center gap-2 sm:gap-3 text-sm shrink-0">
      <a href="/modules/panel/index.php" class="hidden sm:inline bg-muted hover:bg-secondary text-slate-200 px-3.5 py-2 rounded-lg transition-colors">← Centro de transmisión</a>
      <a href="/modules/panel/cuenta.php" class="bg-muted hover:bg-secondary text-slate-200 px-3.5 py-2 rounded-lg transition-colors">Mi cuenta</a>
      <a href="/modules/panel/logout.php" class="bg-destructive/15 hover:bg-destructive/25 text-red-300 px-3.5 py-2 rounded-lg transition-colors">Salir</a>
    </div>
  </div>
  <nav class="flex gap-2 text-sm px-4 sm:px-6 pb-3 sm:pb-4 overflow-x-auto">
    <?php foreach ($tabs as $clave => $etiqueta): ?>
      <a href="/modules/admin/<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>.php"
         class="<?= $activo === $clave
           ? 'bg-accent text-on-accent font-medium px-4 py-2 rounded-lg whitespace-nowrap shrink-0'
           : 'bg-muted hover:bg-secondary text-slate-300 px-4 py-2 rounded-lg transition-colors whitespace-nowrap shrink-0' ?>">
        <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </nav>
</header>
<?php $alertasEndpoint = '/modules/admin/api/solicitudes_nuevas.php'; require __DIR__ . '/../_alertas_solicitud.php'; ?>
