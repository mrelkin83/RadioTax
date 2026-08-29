<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Core\Http;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;

Auth::verificarCsrf();

$empresaId = $usuarioActual['empresa_id'];
ConectorMotor::conectar($empresaId);
$db = Engine::db();

$proveedor = (string) ($_POST['proveedor'] ?? '');
$apikey = trim((string) ($_POST['apikey'] ?? ''));
if ($apikey === '') {
    $apikey = WaConfig::secreto(WaConfig::cargar($db, true), 'llm_api_key');
}
if ($apikey === '') {
    echo json_encode(['ok' => false, 'error' => 'Ingresá la API key (o guardá una primero) para poder consultar los modelos disponibles.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$modelos = [];
$error = '';

switch ($proveedor) {
    case 'anthropic':
        $r = Http::json('GET', 'https://api.anthropic.com/v1/models?limit=200', [
            'x-api-key: ' . $apikey,
            'anthropic-version: 2023-06-01',
        ], null, 20);
        if ($r['ok']) {
            foreach (($r['json']['data'] ?? []) as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $modelos[] = ['id' => $id, 'label' => (string) ($m['display_name'] ?? $id)];
            }
        } else {
            $error = $r['error'] ?: 'No se pudo consultar Anthropic.';
        }
        break;

    case 'gemini':
        $r = Http::json('GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200&key=' . rawurlencode($apikey), [], null, 20);
        if ($r['ok']) {
            foreach (($r['json']['models'] ?? []) as $m) {
                $metodos = $m['supportedGenerationMethods'] ?? [];
                if (!in_array('generateContent', $metodos, true)) {
                    continue;
                }
                $id = str_replace('models/', '', (string) ($m['name'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $modelos[] = ['id' => $id, 'label' => (string) ($m['displayName'] ?? $id)];
            }
        } else {
            $error = $r['error'] ?: 'No se pudo consultar Gemini.';
        }
        break;

    case 'openai':
        $r = Http::json('GET', 'https://api.openai.com/v1/models', [
            'Authorization: Bearer ' . $apikey,
        ], null, 20);
        if ($r['ok']) {
            foreach (($r['json']['data'] ?? []) as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '' || preg_match('/embedding|whisper|tts|moderation|dall-e|davinci-00|babbage|ada-00/i', $id)) {
                    continue;
                }
                $modelos[] = ['id' => $id, 'label' => $id];
            }
            usort($modelos, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));
        } else {
            $error = $r['error'] ?: 'No se pudo consultar OpenAI.';
        }
        break;

    default:
        $error = 'Proveedor no reconocido.';
}

if ($error !== '') {
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'modelos' => $modelos], JSON_UNESCAPED_UNICODE);
