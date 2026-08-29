<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;

$empresaId = $usuarioActual['empresa_id'];
$error = null;
$tokenNuevo = null;
$qrBase64 = null;
$qrCodigo = null;
$conectarError = null;
$desconectarError = null;
$desconectarOk = false;
$guardadoSeccion = null;

ConectorMotor::conectar($empresaId);
$db = Engine::db();

function urlWebhookActual(string $token): string
{
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $esquema . '://' . $host . '/modules/webhook/mensajes.php?token=' . $token;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::csrfValido()) {
    $error = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'guardar_evolution') {
        WaConfig::guardar($db, [
            'activo' => isset($_POST['activo']) ? 1 : 0,
            'evolution_url' => trim((string) ($_POST['evolution_url'] ?? '')),
            'evolution_instancia' => trim((string) ($_POST['evolution_instancia'] ?? '')),
            'evolution_apikey' => trim((string) ($_POST['evolution_apikey'] ?? '')),
            'numero_whatsapp' => trim((string) ($_POST['numero_whatsapp'] ?? '')),
        ]);
        header('Location: /modules/admin/whatsapp.php?guardado=evolution');
        exit;
    }

    if ($accion === 'guardar_ia') {
        WaConfig::guardar($db, [
            'llm_proveedor' => trim((string) ($_POST['llm_proveedor'] ?? '')),
            'llm_modelo' => trim((string) ($_POST['llm_modelo'] ?? '')),
            'llm_api_key' => trim((string) ($_POST['llm_api_key'] ?? '')),
        ]);
        header('Location: /modules/admin/whatsapp.php?guardado=ia');
        exit;
    }

    if ($accion === 'guardar_aviso') {
        WaConfig::guardar($db, [
            'handoff_numero' => trim((string) ($_POST['handoff_numero'] ?? '')),
        ]);
        header('Location: /modules/admin/whatsapp.php?guardado=aviso');
        exit;
    }

    if ($accion === 'regenerar_token') {
        $tokenNuevo = WaConfig::regenerarWebhookToken($db);
    }

    if ($accion === 'conectar') {
        $cliente = EvolutionClient::desdeConfig($db);
        if ($cliente === null || $cliente->requisitosFaltantes() !== []) {
            $conectarError = 'Falta configurar la URL, la instancia o la API key de Evolution antes de conectar.';
        } else {
            $tokenNuevo = WaConfig::regenerarWebhookToken($db);
            $cliente->registrarWebhook(urlWebhookActual($tokenNuevo));
            $resultado = $cliente->conectar();
            if ($resultado['ok']) {
                $qrBase64 = $resultado['qr'];
                $qrCodigo = $resultado['codigo'];
            } else {
                $conectarError = $resultado['error'];
            }
        }
    }

    if ($accion === 'desconectar') {
        $cliente = EvolutionClient::desdeConfig($db);
        if ($cliente === null) {
            $desconectarError = 'No hay una instancia configurada.';
        } else {
            $resultado = $cliente->desconectar();
            if ($resultado['ok']) {
                $desconectarOk = true;
            } else {
                $desconectarError = $resultado['error'];
            }
        }
    }
}

$cfg = WaConfig::paraFrontend($db);
$csrf = Auth::tokenCsrf();
$activo = 'whatsapp';

$evolucionTieneConfig = !empty($cfg['evolution_url']);
$evolucionSoloLectura = $evolucionTieneConfig && !isset($_GET['editar_evolution']);

$iaTieneConfig = !empty($cfg['llm_proveedor']);
$iaSoloLectura = $iaTieneConfig && !isset($_GET['editar_ia']);

$avisoTieneConfig = !empty($cfg['handoff_numero']);
$avisoSoloLectura = $avisoTieneConfig && !isset($_GET['editar_aviso']);

if (isset($_GET['guardado'])) {
    $guardadoSeccion = (string) $_GET['guardado'];
}

$estadoConexion = null;
if ($evolucionTieneConfig && $qrBase64 === null) {
    $clienteEstado = EvolutionClient::desdeConfig($db);
    if ($clienteEstado !== null && $clienteEstado->requisitosFaltantes() === []) {
        try {
            $estadoConexion = $clienteEstado->estado();
        } catch (\Throwable $e) {
            $estadoConexion = null;
        }
    }
}

$mensajesGuardado = ['evolution' => 'Evolution API guardado.', 'ia' => 'Proveedor de IA guardado.', 'aviso' => 'Aviso al radiooperador guardado.'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<title>WhatsApp · Administración · <?= htmlspecialchars($usuarioActual['empresa_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<?php require __DIR__ . '/../_tema.php'; ?>
</head>
<body class="bg-background text-foreground min-h-screen">
  <?php require __DIR__ . '/_nav.php'; ?>

  <main class="p-6 max-w-4xl mx-auto space-y-6">
    <?php if ($error !== null): ?>
      <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-base rounded-lg px-4 py-3" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($guardadoSeccion !== null && isset($mensajesGuardado[$guardadoSeccion])): ?>
      <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-base rounded-lg px-4 py-3"><?= htmlspecialchars($mensajesGuardado[$guardadoSeccion], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($tokenNuevo !== null): ?>
      <div class="bg-warning/10 border border-warning/30 text-amber-200 text-base rounded-xl p-6 space-y-3">
        <p class="font-medium">Token del webhook generado — guárdalo ahora, no se vuelve a mostrar:</p>
        <code class="block bg-muted border border-border rounded-lg p-3 break-all font-mono text-sm"><?= htmlspecialchars($tokenNuevo, ENT_QUOTES, 'UTF-8') ?></code>
        <p>URL del webhook para configurar en Evolution API:</p>
        <code class="block bg-muted border border-border rounded-lg p-3 break-all font-mono text-sm"><?= htmlspecialchars(urlWebhookActual($tokenNuevo), ENT_QUOTES, 'UTF-8') ?></code>
      </div>
    <?php endif; ?>

    <!-- Fila 1: token del webhook + conectar WhatsApp -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
      <section class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-lg mb-2">Token del webhook</h2>
        <p class="text-sm text-slate-400 mb-4">
          <?= !empty($cfg['webhook_token_hash_configurado']) || $tokenNuevo !== null ? 'Ya hay uno configurado.' : 'Todavía no se ha generado.' ?>
          Regenerarlo invalida el anterior — hay que actualizar la URL en Evolution API.
        </p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="accion" value="regenerar_token">
          <button type="submit" class="bg-warning/15 hover:bg-warning/25 text-amber-200 text-base font-medium rounded-lg px-5 py-2.5">
            Generar nuevo token
          </button>
        </form>
      </section>

      <?php if ($evolucionTieneConfig): ?>
        <section class="bg-card border border-border rounded-xl p-6 space-y-4">
          <h2 class="font-semibold text-lg">Conectar WhatsApp</h2>

          <?php if ($conectarError !== null): ?>
            <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-sm rounded-lg px-4 py-3"><?= htmlspecialchars($conectarError, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <?php if ($desconectarOk): ?>
            <p class="bg-accent/10 border border-accent/30 text-emerald-300 text-sm rounded-lg px-4 py-3">WhatsApp desconectado.</p>
          <?php endif; ?>
          <?php if ($desconectarError !== null): ?>
            <p class="bg-destructive/10 border border-destructive/30 text-red-300 text-sm rounded-lg px-4 py-3"><?= htmlspecialchars($desconectarError, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>

          <?php if ($qrBase64 !== null): ?>
            <p class="text-sm text-slate-400">Escaneá este código con WhatsApp (Dispositivos vinculados → Vincular un dispositivo). Se actualiza cada vez que tocás "Conectar WhatsApp".</p>
            <img src="<?= htmlspecialchars($qrBase64, ENT_QUOTES, 'UTF-8') ?>" alt="Código QR para vincular WhatsApp" class="rounded-lg border border-border w-56 h-56 bg-white p-2">
          <?php elseif ($estadoConexion !== null): ?>
            <p class="text-sm text-slate-400">
              Estado:
              <?php if ($estadoConexion['estado'] === 'conectado'): ?>
                <span class="text-emerald-400 font-medium">conectado<?= !empty($estadoConexion['numero']) ? ' (' . htmlspecialchars((string) $estadoConexion['numero'], ENT_QUOTES, 'UTF-8') . ')' : '' ?></span>
              <?php elseif ($estadoConexion['estado'] === 'qr'): ?>
                <span class="text-amber-400 font-medium">esperando que se escanee el QR</span>
              <?php elseif ($estadoConexion['estado'] === 'error'): ?>
                <span class="text-red-400 font-medium">no se pudo consultar (<?= htmlspecialchars((string) $estadoConexion['mensaje'], ENT_QUOTES, 'UTF-8') ?>)</span>
              <?php else: ?>
                <span class="text-slate-300 font-medium">desconectado</span>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <div class="flex gap-3">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="accion" value="conectar">
              <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">
                <?= $qrBase64 !== null || ($estadoConexion['estado'] ?? '') === 'qr' ? 'Generar QR de nuevo' : 'Conectar WhatsApp' ?>
              </button>
            </form>
            <?php if (($estadoConexion['estado'] ?? '') === 'conectado'): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accion" value="desconectar">
                <button type="submit" class="bg-destructive/15 hover:bg-destructive/25 text-red-300 text-base font-medium rounded-lg px-5 py-2.5">Desconectar</button>
              </form>
            <?php endif; ?>
          </div>
        </section>
      <?php else: ?>
        <section class="bg-card border border-border rounded-xl p-6 flex items-center">
          <p class="text-sm text-slate-500">Guardá primero la configuración de Evolution API para poder conectar un WhatsApp.</p>
        </section>
      <?php endif; ?>
    </div>

    <!-- Fila 2: Evolution API + Proveedor de IA -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
      <section class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-lg mb-4">Evolution API</h2>
        <form method="post" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="accion" value="guardar_evolution">

          <?php if ($evolucionSoloLectura): ?>
            <p class="text-sm text-slate-400">Configuración guardada. Tocá "Editar" para cambiarla.</p>
          <?php endif; ?>

          <fieldset <?= $evolucionSoloLectura ? 'disabled' : '' ?> class="space-y-4 <?= $evolucionSoloLectura ? 'opacity-60' : '' ?>">
            <label class="flex items-center gap-3 text-base">
              <input type="checkbox" name="activo" <?= !empty($cfg['activo']) ? 'checked' : '' ?> class="accent-accent w-5 h-5">
              Motor encendido
            </label>
            <div>
              <label for="ev_url" class="block text-sm text-slate-400 mb-2">URL</label>
              <input id="ev_url" name="evolution_url" placeholder="http://localhost:8080" value="<?= htmlspecialchars((string) ($cfg['evolution_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base font-mono">
            </div>
            <div>
              <label for="ev_instancia" class="block text-sm text-slate-400 mb-2">Nombre de la instancia</label>
              <input id="ev_instancia" name="evolution_instancia" placeholder="radiotax" value="<?= htmlspecialchars((string) ($cfg['evolution_instancia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
            </div>
            <div>
              <label for="ev_numero" class="block text-sm text-slate-400 mb-2">Número de WhatsApp</label>
              <input id="ev_numero" name="numero_whatsapp" value="<?= htmlspecialchars((string) ($cfg['numero_whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base font-mono">
            </div>
            <div>
              <label for="ev_apikey" class="block text-sm text-slate-400 mb-2">API Key</label>
              <input id="ev_apikey" name="evolution_apikey" type="password" placeholder="<?= !empty($cfg['evolution_apikey_configurado']) ? 'Ya hay una guardada' : 'API Key' ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base font-mono">
            </div>
          </fieldset>

          <?php if ($evolucionSoloLectura): ?>
            <a href="?editar_evolution=1" class="inline-block bg-muted hover:bg-secondary text-slate-100 text-base font-medium rounded-lg px-5 py-2.5">Editar</a>
          <?php else: ?>
            <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Guardar</button>
          <?php endif; ?>
        </form>
      </section>

      <section class="bg-card border border-border rounded-xl p-6">
        <h2 class="font-semibold text-lg mb-4">Proveedor de IA</h2>
        <form method="post" class="space-y-4" id="form-ia">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="accion" value="guardar_ia">

          <?php if ($iaSoloLectura): ?>
            <p class="text-sm text-slate-400">Configuración guardada. Tocá "Editar" para cambiarla.</p>
          <?php endif; ?>

          <fieldset <?= $iaSoloLectura ? 'disabled' : '' ?> class="space-y-4 <?= $iaSoloLectura ? 'opacity-60' : '' ?>">
            <div>
              <label for="ia_proveedor" class="block text-sm text-slate-400 mb-2">Proveedor</label>
              <select id="ia_proveedor" name="llm_proveedor" class="w-full rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base">
                <?php foreach (['' => 'Selecciona…', 'anthropic' => 'Anthropic', 'gemini' => 'Gemini', 'openai' => 'OpenAI / compatible'] as $valor => $etiqueta): ?>
                  <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>" <?= ($cfg['llm_proveedor'] ?? '') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <div class="flex items-center justify-between mb-2">
                <label for="ia_modelo" class="block text-sm text-slate-400">Modelo</label>
                <button type="button" id="btn-cargar-modelos" class="text-xs text-slate-400 hover:text-slate-200 underline">Actualizar lista</button>
              </div>
              <select id="ia_modelo" name="llm_modelo" class="w-full rounded-lg bg-muted border border-border text-foreground px-4 py-2.5 text-base font-mono">
                <?php if (!empty($cfg['llm_modelo'])): ?>
                  <option value="<?= htmlspecialchars((string) $cfg['llm_modelo'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $cfg['llm_modelo'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php else: ?>
                  <option value="">Elegí el proveedor primero…</option>
                <?php endif; ?>
              </select>
              <p id="msg-modelos" class="text-xs text-slate-500 mt-1.5"></p>
            </div>
            <div>
              <label for="ia_apikey" class="block text-sm text-slate-400 mb-2">API Key</label>
              <input id="ia_apikey" name="llm_api_key" type="password" placeholder="<?= !empty($cfg['llm_api_key_configurado']) ? 'Ya hay una guardada' : 'API Key' ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base">
            </div>
          </fieldset>

          <?php if ($iaSoloLectura): ?>
            <a href="?editar_ia=1" class="inline-block bg-muted hover:bg-secondary text-slate-100 text-base font-medium rounded-lg px-5 py-2.5">Editar</a>
          <?php else: ?>
            <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Guardar</button>
          <?php endif; ?>
        </form>
      </section>
    </div>

    <!-- Fila 3: aviso al radiooperador -->
    <section class="bg-card border border-border rounded-xl p-6">
      <h2 class="font-semibold text-lg mb-4">Aviso al radiooperador</h2>
      <form method="post" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="guardar_aviso">

        <?php if ($avisoSoloLectura): ?>
          <p class="text-sm text-slate-400">Configuración guardada. Tocá "Editar" para cambiarla.</p>
        <?php endif; ?>

        <fieldset <?= $avisoSoloLectura ? 'disabled' : '' ?> class="<?= $avisoSoloLectura ? 'opacity-60' : '' ?>">
          <label for="aviso_numero" class="block text-sm text-slate-400 mb-2">Número que recibe el aviso cuando se transfiere a un humano</label>
          <input id="aviso_numero" name="handoff_numero" value="<?= htmlspecialchars((string) ($cfg['handoff_numero'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-muted border border-border text-foreground placeholder:text-slate-500 px-4 py-2.5 text-base font-mono">
        </fieldset>

        <?php if ($avisoSoloLectura): ?>
          <a href="?editar_aviso=1" class="inline-block bg-muted hover:bg-secondary text-slate-100 text-base font-medium rounded-lg px-5 py-2.5">Editar</a>
        <?php else: ?>
          <button type="submit" class="bg-accent hover:bg-accent-hover text-on-accent text-base font-medium rounded-lg px-5 py-2.5">Guardar</button>
        <?php endif; ?>
      </form>
    </section>
  </main>

  <script>
  (() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const selProveedor = document.getElementById('ia_proveedor');
    const selModelo = document.getElementById('ia_modelo');
    const inputApikey = document.getElementById('ia_apikey');
    const btnCargar = document.getElementById('btn-cargar-modelos');
    const msg = document.getElementById('msg-modelos');
    if (!selProveedor || selProveedor.disabled) return; // bloqueado: no hace falta cargar nada

    async function cargarModelos() {
      if (!selProveedor.value) {
        msg.textContent = 'Elegí un proveedor primero.';
        return;
      }
      const valorActual = selModelo.value;
      selModelo.disabled = true;
      msg.textContent = 'Cargando modelos…';
      try {
        const resp = await fetch('/modules/admin/api/modelos_ia.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ csrf, proveedor: selProveedor.value, apikey: inputApikey.value }),
        });
        const datos = await resp.json();
        if (!datos.ok) {
          msg.textContent = datos.error || 'No se pudieron cargar los modelos.';
          return;
        }
        selModelo.innerHTML = '';
        const yaEstaba = datos.modelos.some((m) => m.id === valorActual);
        if (valorActual && !yaEstaba) {
          const opt = document.createElement('option');
          opt.value = valorActual;
          opt.textContent = valorActual + ' (actual)';
          opt.selected = true;
          selModelo.appendChild(opt);
        }
        for (const m of datos.modelos) {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = m.label;
          if (m.id === valorActual) opt.selected = true;
          selModelo.appendChild(opt);
        }
        msg.textContent = datos.modelos.length + ' modelos disponibles.';
      } catch (e) {
        msg.textContent = 'No se pudo conectar para cargar los modelos.';
      } finally {
        selModelo.disabled = false;
      }
    }

    selProveedor.addEventListener('change', cargarModelos);
    btnCargar?.addEventListener('click', cargarModelos);
  })();
  </script>
</body>
</html>
