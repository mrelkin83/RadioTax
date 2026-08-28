<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;

$empresaId = $usuarioActual['empresa_id'];
$error = null;
$tokenNuevo = null;

ConectorMotor::conectar($empresaId);
$db = Engine::db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'guardar') {
        WaConfig::guardar($db, [
            'activo' => isset($_POST['activo']) ? 1 : 0,
            'evolution_url' => trim((string) ($_POST['evolution_url'] ?? '')),
            'evolution_instancia' => trim((string) ($_POST['evolution_instancia'] ?? '')),
            'evolution_apikey' => trim((string) ($_POST['evolution_apikey'] ?? '')),
            'numero_whatsapp' => trim((string) ($_POST['numero_whatsapp'] ?? '')),
            'llm_proveedor' => trim((string) ($_POST['llm_proveedor'] ?? '')),
            'llm_modelo' => trim((string) ($_POST['llm_modelo'] ?? '')),
            'llm_api_key' => trim((string) ($_POST['llm_api_key'] ?? '')),
            'handoff_numero' => trim((string) ($_POST['handoff_numero'] ?? '')),
        ]);
        header('Location: /modules/admin/whatsapp.php?guardado=1');
        exit;
    }

    if ($accion === 'regenerar_token') {
        $tokenNuevo = WaConfig::regenerarWebhookToken($db);
    }
}

$cfg = WaConfig::paraFrontend($db);
$csrf = Auth::tokenCsrf();
$activo = 'whatsapp';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WhatsApp · Administración · Radio Tax</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-2xl mx-auto space-y-6">
    <?php if ($error !== null): ?>
      <p class="bg-red-900/50 text-red-200 text-sm rounded p-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['guardado'])): ?>
      <p class="bg-emerald-900/50 text-emerald-200 text-sm rounded p-3">Configuración guardada.</p>
    <?php endif; ?>

    <?php if ($tokenNuevo !== null): ?>
      <div class="bg-amber-900/40 border border-amber-700 text-amber-100 text-sm rounded p-4 space-y-2">
        <p class="font-medium">Token del webhook generado — guárdalo ahora, no se vuelve a mostrar:</p>
        <code class="block bg-slate-900 rounded p-2 break-all"><?= htmlspecialchars($tokenNuevo, ENT_QUOTES, 'UTF-8') ?></code>
        <p>URL del webhook para configurar en Evolution API:</p>
        <code class="block bg-slate-900 rounded p-2 break-all">https://TU-DOMINIO/modules/webhook/mensajes.php?token=<?= htmlspecialchars($tokenNuevo, ENT_QUOTES, 'UTF-8') ?></code>
      </div>
    <?php endif; ?>

    <section class="bg-slate-800 rounded-lg p-4">
      <h2 class="font-semibold mb-1">Token del webhook</h2>
      <p class="text-xs text-slate-400 mb-3">
        <?= !empty($cfg['webhook_token_hash']) || $tokenNuevo !== null ? 'Ya hay uno configurado.' : 'Todavía no se ha generado.' ?>
        Regenerarlo invalida el anterior — hay que actualizar la URL en Evolution API.
      </p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="regenerar_token">
        <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-sm rounded px-4 py-2">
          Generar nuevo token
        </button>
      </form>
    </section>

    <form method="post" class="bg-slate-800 rounded-lg p-4 space-y-4">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="accion" value="guardar">

      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="activo" <?= !empty($cfg['activo']) ? 'checked' : '' ?>>
        Motor encendido (si está apagado, los mensajes se reciben y se ignoran)
      </label>

      <div>
        <h3 class="text-sm font-semibold text-slate-300 mb-2">Evolution API</h3>
        <div class="grid grid-cols-2 gap-2">
          <input name="evolution_url" placeholder="URL (ej. http://localhost:8080)" value="<?= htmlspecialchars((string) ($cfg['evolution_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="col-span-2 rounded bg-slate-700 text-white px-3 py-2 text-sm">
          <input name="evolution_instancia" placeholder="Nombre de la instancia" value="<?= htmlspecialchars((string) ($cfg['evolution_instancia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded bg-slate-700 text-white px-3 py-2 text-sm">
          <input name="numero_whatsapp" placeholder="Número de WhatsApp" value="<?= htmlspecialchars((string) ($cfg['numero_whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded bg-slate-700 text-white px-3 py-2 text-sm">
          <input name="evolution_apikey" type="password" placeholder="<?= !empty($cfg['evolution_apikey_configurado']) ? 'API Key (ya hay una guardada)' : 'API Key' ?>" class="col-span-2 rounded bg-slate-700 text-white px-3 py-2 text-sm">
        </div>
      </div>

      <div>
        <h3 class="text-sm font-semibold text-slate-300 mb-2">Proveedor de IA</h3>
        <div class="grid grid-cols-2 gap-2">
          <select name="llm_proveedor" class="rounded bg-slate-700 text-white px-3 py-2 text-sm">
            <?php foreach (['' => 'Selecciona…', 'anthropic' => 'Anthropic', 'gemini' => 'Gemini', 'openai' => 'OpenAI / compatible'] as $valor => $etiqueta): ?>
              <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>" <?= ($cfg['llm_proveedor'] ?? '') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <input name="llm_modelo" placeholder="Modelo (ej. claude-3-5-haiku-20241022)" value="<?= htmlspecialchars((string) ($cfg['llm_modelo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded bg-slate-700 text-white px-3 py-2 text-sm">
          <input name="llm_api_key" type="password" placeholder="<?= !empty($cfg['llm_api_key_configurado']) ? 'API Key (ya hay una guardada)' : 'API Key' ?>" class="col-span-2 rounded bg-slate-700 text-white px-3 py-2 text-sm">
        </div>
      </div>

      <div>
        <h3 class="text-sm font-semibold text-slate-300 mb-2">Aviso al radiooperador</h3>
        <input name="handoff_numero" placeholder="Número que recibe el aviso cuando se transfiere a un humano" value="<?= htmlspecialchars((string) ($cfg['handoff_numero'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded bg-slate-700 text-white px-3 py-2 text-sm">
      </div>

      <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium rounded px-4 py-2">Guardar</button>
    </form>
  </main>
</body>
</html>
