<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$desde = (string) ($_GET['desde'] ?? date('Y-m-d', strtotime('-30 days')));
$hasta = (string) ($_GET['hasta'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = date('Y-m-d');
}
$hastaFin = $hasta . ' 23:59:59';

function contar(PDO $pdo, string $sql, array $params): int
{
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return (int) $s->fetchColumn();
}

$rango = ['empresa' => $empresaId, 'desde' => $desde, 'hasta' => $hastaFin];

// ── Solicitudes por estado ──────────────────────────────────────────────
$totalSolicitudes = contar($pdo,
    'SELECT COUNT(*) FROM tx_carreras WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta',
    $rango);
$finalizadas = contar($pdo,
    "SELECT COUNT(*) FROM tx_carreras WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta AND estado = 'FINALIZADA'",
    $rango);
$canceladas = contar($pdo,
    "SELECT COUNT(*) FROM tx_carreras WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta AND estado = 'CANCELADA'",
    $rango);
$noAtendidas = contar($pdo,
    "SELECT COUNT(*) FROM tx_carreras WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta AND estado = 'NO_ATENDIDA'",
    $rango);
$enCurso = $totalSolicitudes - $finalizadas - $canceladas - $noAtendidas;

$rechazadas = contar($pdo,
    "SELECT COUNT(*) FROM tx_asignaciones a
     INNER JOIN tx_carreras c ON c.id = a.carrera_id
     WHERE c.empresa_id = :empresa AND c.creado_en BETWEEN :desde AND :hasta AND a.resultado = 'RECHAZADA'",
    $rango);

// ── % de automatización: quién creó la carrera ──────────────────────────
$creadasPorIa = contar($pdo,
    "SELECT COUNT(DISTINCT e.carrera_id) FROM tx_carrera_eventos e
     INNER JOIN tx_carreras c ON c.id = e.carrera_id
     WHERE c.empresa_id = :empresa AND c.creado_en BETWEEN :desde AND :hasta
       AND e.evento = 'CARRERA_RECIBIDA' AND e.actor_tipo = 'IA'",
    $rango);
$creadasPorOperador = $totalSolicitudes - $creadasPorIa;
$pctAutomatizacion = $totalSolicitudes > 0 ? round($creadasPorIa / $totalSolicitudes * 100, 1) : 0.0;

// ── Tiempos ──────────────────────────────────────────────────────────────
$tiempoAsignacion = $pdo->prepare(
    "SELECT AVG(TIMESTAMPDIFF(SECOND, creado_en, asignada_en)) FROM tx_carreras
     WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta AND asignada_en IS NOT NULL"
);
$tiempoAsignacion->execute($rango);
$segAsignacion = $tiempoAsignacion->fetchColumn();
$segAsignacion = $segAsignacion !== null ? (int) round((float) $segAsignacion) : null;

// ── Servicios por tipo ───────────────────────────────────────────────────
$sentencia = $pdo->prepare(
    'SELECT tipo_servicio, COUNT(*) AS total FROM tx_carreras
     WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta
     GROUP BY tipo_servicio ORDER BY total DESC'
);
$sentencia->execute($rango);
$porTipo = $sentencia->fetchAll();

// ── Top vehículos ────────────────────────────────────────────────────────
$sentencia = $pdo->prepare(
    "SELECT v.numero_interno, v.placa, COUNT(*) AS total FROM tx_carreras c
     INNER JOIN tx_vehiculos v ON v.id = c.vehiculo_id
     WHERE c.empresa_id = :empresa AND c.creado_en BETWEEN :desde AND :hasta AND c.estado = 'FINALIZADA'
     GROUP BY v.id ORDER BY total DESC LIMIT 10"
);
$sentencia->execute($rango);
$topVehiculos = $sentencia->fetchAll();

// ── Top conductores ──────────────────────────────────────────────────────
$sentencia = $pdo->prepare(
    "SELECT cond.nombre, COUNT(*) AS total FROM tx_carreras c
     INNER JOIN tx_conductores cond ON cond.id = c.conductor_id
     WHERE c.empresa_id = :empresa AND c.creado_en BETWEEN :desde AND :hasta AND c.estado = 'FINALIZADA'
     GROUP BY cond.id ORDER BY total DESC LIMIT 10"
);
$sentencia->execute($rango);
$topConductores = $sentencia->fetchAll();

// ── Direcciones de recogida más frecuentes (zonas de demanda) ───────────
$sentencia = $pdo->prepare(
    'SELECT recogida_texto, COUNT(*) AS total FROM tx_carreras
     WHERE empresa_id = :empresa AND creado_en BETWEEN :desde AND :hasta
     GROUP BY recogida_texto ORDER BY total DESC LIMIT 10'
);
$sentencia->execute($rango);
$topRecogidas = $sentencia->fetchAll();

$activo = 'reportes';

function formatoDuracion(?int $segundos): string
{
    if ($segundos === null) {
        return '—';
    }
    if ($segundos < 60) {
        return "{$segundos} s";
    }
    return round($segundos / 60, 1) . ' min';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reportes · Administración · <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-7xl mx-auto space-y-8">
    <form method="get" class="flex items-end gap-3 bg-card border border-border rounded-xl p-6">
      <div>
        <label class="block text-sm text-slate-400 mb-2">Desde</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base font-mono">
      </div>
      <div>
        <label class="block text-sm text-slate-400 mb-2">Hasta</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base font-mono">
      </div>
      <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Filtrar</button>
    </form>

    <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">Solicitudes totales</p>
        <p class="text-4xl font-semibold text-foreground font-mono mt-1"><?= $totalSolicitudes ?></p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">Finalizadas</p>
        <p class="text-4xl font-semibold text-yellow-400 font-mono mt-1"><?= $finalizadas ?></p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">Canceladas</p>
        <p class="text-4xl font-semibold text-red-400 font-mono mt-1"><?= $canceladas ?></p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">No atendidas</p>
        <p class="text-4xl font-semibold text-amber-400 font-mono mt-1"><?= $noAtendidas ?></p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">En curso</p>
        <p class="text-4xl font-semibold text-sky-400 font-mono mt-1"><?= max(0, $enCurso) ?></p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">Asignaciones rechazadas</p>
        <p class="text-4xl font-semibold text-foreground font-mono mt-1"><?= $rechazadas ?></p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">% automatización (creadas por IA)</p>
        <p class="text-4xl font-semibold text-foreground font-mono mt-1"><?= $pctAutomatizacion ?>%</p>
        <p class="text-sm text-slate-500 mt-2"><?= $creadasPorIa ?> IA · <?= $creadasPorOperador ?> radiooperador</p>
      </div>
      <div class="bg-card border border-border rounded-xl p-6">
        <p class="text-sm text-slate-400">Tiempo promedio hasta asignar</p>
        <p class="text-4xl font-semibold text-foreground font-mono mt-1"><?= htmlspecialchars(formatoDuracion($segAsignacion), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-base text-slate-300 uppercase tracking-wide mb-4">Servicios por tipo</h2>
        <?php if ($porTipo === []): ?>
          <p class="text-slate-500 text-base">Sin datos en este rango.</p>
        <?php endif; ?>
        <?php foreach ($porTipo as $fila): ?>
          <div class="flex justify-between text-base py-2.5 border-b border-border last:border-0">
            <span><?= htmlspecialchars($fila['tipo_servicio'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-slate-400 font-mono"><?= (int) $fila['total'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-base text-slate-300 uppercase tracking-wide mb-4">Direcciones de recogida más frecuentes</h2>
        <?php if ($topRecogidas === []): ?>
          <p class="text-slate-500 text-base">Sin datos en este rango.</p>
        <?php endif; ?>
        <?php foreach ($topRecogidas as $fila): ?>
          <div class="flex justify-between text-base py-2.5 border-b border-border last:border-0">
            <span class="truncate pr-2"><?= htmlspecialchars($fila['recogida_texto'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-slate-400 shrink-0 font-mono"><?= (int) $fila['total'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-base text-slate-300 uppercase tracking-wide mb-4">Vehículos con más servicios completados</h2>
        <?php if ($topVehiculos === []): ?>
          <p class="text-slate-500 text-base">Sin servicios finalizados en este rango.</p>
        <?php endif; ?>
        <?php foreach ($topVehiculos as $fila): ?>
          <div class="flex justify-between text-base py-2.5 border-b border-border last:border-0">
            <span><?= htmlspecialchars($fila['numero_interno'], ENT_QUOTES, 'UTF-8') ?> · <span class="font-mono"><?= htmlspecialchars($fila['placa'], ENT_QUOTES, 'UTF-8') ?></span></span>
            <span class="text-slate-400 font-mono"><?= (int) $fila['total'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-base text-slate-300 uppercase tracking-wide mb-4">Conductores con más servicios completados</h2>
        <?php if ($topConductores === []): ?>
          <p class="text-slate-500 text-base">Sin servicios finalizados en este rango.</p>
        <?php endif; ?>
        <?php foreach ($topConductores as $fila): ?>
          <div class="flex justify-between text-base py-2.5 border-b border-border last:border-0">
            <span><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-slate-400 font-mono"><?= (int) $fila['total'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
