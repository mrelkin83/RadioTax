<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

$pdo = Database::conexion();
$error = null;
$credencialesNuevas = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear_empresa') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $ciudad = trim((string) ($_POST['ciudad'] ?? ''));
        if ($nombre === '' || $ciudad === '') {
            $error = 'Nombre y ciudad son obligatorios.';
        } else {
            $pdo->prepare(
                "INSERT INTO tx_empresas (nombre, ciudad, modo_despacho_default) VALUES (:nombre, :ciudad, 'HIBRIDO')"
            )->execute(['nombre' => $nombre, 'ciudad' => $ciudad]);
            header('Location: /modules/plataforma/empresas.php?creada=1');
            exit;
        }
    } elseif ($accion === 'crear_linea') {
        $empresaId = (int) ($_POST['empresa_id'] ?? 0);
        $nombreLinea = trim((string) ($_POST['nombre_linea'] ?? ''));
        if ($empresaId <= 0 || $nombreLinea === '') {
            $error = 'Falta el nombre de la línea.';
        } else {
            $pdo->prepare(
                'INSERT INTO tx_lineas (empresa_id, nombre, instancia_evolution, token_webhook, agentes_max)
                 VALUES (:empresa, :nombre, :instancia, :token, 1)'
            )->execute([
                'empresa' => $empresaId,
                'nombre' => $nombreLinea,
                'instancia' => 'linea-' . $empresaId . '-' . bin2hex(random_bytes(3)),
                'token' => bin2hex(random_bytes(16)),
            ]);
            header('Location: /modules/plataforma/empresas.php?linea=1');
            exit;
        }
    } elseif ($accion === 'crear_admin') {
        $empresaId = (int) ($_POST['empresa_id'] ?? 0);
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        if ($empresaId <= 0 || $nombreUsuario === '' || $usuario === '') {
            $error = 'Nombre, usuario y empresa son obligatorios.';
        } else {
            $sentencia = $pdo->prepare('SELECT id FROM tx_usuarios WHERE usuario = :usuario LIMIT 1');
            $sentencia->execute(['usuario' => $usuario]);
            if ($sentencia->fetchColumn() !== false) {
                $error = "El usuario \"{$usuario}\" ya existe — elige otro nombre de usuario.";
            } else {
                $clave = bin2hex(random_bytes(6));
                $pdo->prepare(
                    "INSERT INTO tx_usuarios (empresa_id, nombre, usuario, clave_hash, rol) VALUES (:empresa, :nombre, :usuario, :hash, 'ADMIN')"
                )->execute([
                    'empresa' => $empresaId,
                    'nombre' => $nombreUsuario,
                    'usuario' => $usuario,
                    'hash' => password_hash($clave, PASSWORD_DEFAULT),
                ]);
                $credencialesNuevas = ['usuario' => $usuario, 'clave' => $clave];
            }
        }
    }
}

$empresas = $pdo->query(
    "SELECT e.id, e.nombre, e.ciudad, e.modo_despacho_default,
            (SELECT COUNT(*) FROM tx_lineas l WHERE l.empresa_id = e.id) AS total_lineas,
            (SELECT COUNT(*) FROM tx_usuarios u WHERE u.empresa_id = e.id) AS total_usuarios,
            (SELECT COUNT(*) FROM tx_vehiculos v WHERE v.empresa_id = e.id) AS total_vehiculos
     FROM tx_empresas e ORDER BY e.id ASC"
)->fetchAll();

$csrf = Auth::tokenCsrf();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Empresas · Plataforma</title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <header class="border-b border-border px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-3 bg-card/90 backdrop-blur">
    <div class="flex items-center gap-2.5 min-w-0">
      <svg class="w-5 h-5 text-yellow-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 3v18M3 12h18"/>
      </svg>
      <h1 class="text-base font-semibold truncate">Plataforma <span class="text-slate-500 font-normal">· Empresas (marca blanca)</span></h1>
    </div>
    <div class="flex items-center gap-3 sm:gap-5 text-sm text-slate-400 shrink-0">
      <span class="hidden md:inline"><?= htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES, 'UTF-8') ?> <span class="text-slate-600">·</span> SUPERADMIN</span>
      <a href="/modules/panel/logout.php" class="text-red-400 hover:text-red-300 transition-colors">Salir</a>
    </div>
  </header>

  <main class="p-6 max-w-4xl mx-auto space-y-6">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-sm rounded-lg px-3 py-2" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['creada'])): ?>
      <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-sm rounded-lg px-3 py-2">Empresa creada. Ahora dale una línea y un usuario admin.</p>
    <?php endif; ?>
    <?php if (isset($_GET['linea'])): ?>
      <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-sm rounded-lg px-3 py-2">Línea creada.</p>
    <?php endif; ?>
    <?php if ($credencialesNuevas !== null): ?>
      <div class="bg-warning/10 border border-warning/30 text-amber-200 text-sm rounded-xl p-4 space-y-1">
        <p class="font-medium">Usuario admin creado — guarda esto ahora, no se vuelve a mostrar:</p>
        <p>Usuario: <code class="bg-muted border border-border rounded px-2 py-0.5 font-mono"><?= htmlspecialchars($credencialesNuevas['usuario'], ENT_QUOTES, 'UTF-8') ?></code></p>
        <p>Clave: <code class="bg-muted border border-border rounded px-2 py-0.5 font-mono"><?= htmlspecialchars($credencialesNuevas['clave'], ENT_QUOTES, 'UTF-8') ?></code></p>
      </div>
    <?php endif; ?>

    <section class="bg-card border border-border rounded-xl p-4">
      <h2 class="font-semibold mb-3">Nueva empresa</h2>
      <form method="post" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="crear_empresa">
        <div>
          <label for="emp_nombre" class="block text-xs text-slate-400 mb-1.5">Nombre</label>
          <input id="emp_nombre" name="nombre" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm">
        </div>
        <div>
          <label for="emp_ciudad" class="block text-xs text-slate-400 mb-1.5">Ciudad</label>
          <input id="emp_ciudad" name="ciudad" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-sm font-medium rounded-lg px-4 py-2">Crear</button>
      </form>
    </section>

    <section class="space-y-4">
      <h2 class="font-semibold text-sm text-neutral-800 uppercase tracking-wide">Empresas (<?= count($empresas) ?>)</h2>
      <?php foreach ($empresas as $e): ?>
        <div class="bg-card border border-border rounded-xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-foreground font-medium"><?= htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8') ?> <span class="text-slate-500 text-sm">· <?= htmlspecialchars($e['ciudad'], ENT_QUOTES, 'UTF-8') ?></span></p>
              <p class="text-xs text-slate-500"><?= $e['total_lineas'] ?> línea(s) · <?= $e['total_usuarios'] ?> usuario(s) · <?= $e['total_vehiculos'] ?> vehículo(s) · modo <?= htmlspecialchars($e['modo_despacho_default'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
          <div class="flex flex-wrap gap-6 pt-3 border-t border-border">
            <form method="post" class="flex flex-wrap items-end gap-2">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="accion" value="crear_linea">
              <input type="hidden" name="empresa_id" value="<?= (int) $e['id'] ?>">
              <div>
                <label for="linea_nombre_<?= (int) $e['id'] ?>" class="block text-xs text-slate-400 mb-1.5">Nueva línea</label>
                <input id="linea_nombre_<?= (int) $e['id'] ?>" name="nombre_linea" placeholder="Línea 1" required class="rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-3 py-2 text-sm w-32">
              </div>
              <button type="submit" class="text-sm bg-muted hover:bg-secondary text-slate-100 font-medium px-3.5 py-2 rounded-lg">+ Línea</button>
            </form>

            <form method="post" class="flex flex-wrap items-end gap-2">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="accion" value="crear_admin">
              <input type="hidden" name="empresa_id" value="<?= (int) $e['id'] ?>">
              <div>
                <label for="admin_nombre_<?= (int) $e['id'] ?>" class="block text-xs text-slate-400 mb-1.5">Nombre del admin</label>
                <input id="admin_nombre_<?= (int) $e['id'] ?>" name="nombre_usuario" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-32">
              </div>
              <div>
                <label for="admin_usuario_<?= (int) $e['id'] ?>" class="block text-xs text-slate-400 mb-1.5">Usuario (login)</label>
                <input id="admin_usuario_<?= (int) $e['id'] ?>" name="usuario" required class="rounded-lg bg-muted border border-border text-foreground px-3 py-2 text-sm w-32 font-mono">
              </div>
              <button type="submit" class="text-sm bg-muted hover:bg-secondary text-slate-100 font-medium px-3.5 py-2 rounded-lg">+ Admin</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
