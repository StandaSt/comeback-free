<?php
declare(strict_types=1);

/*
 * Účel souboru: Přijme jednu diagnostickou událost klienta a při aktivním
 * set_system.log_3 ji přidá k jednotnému DB záznamu tohoto HTTP požadavku.
 */

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
// DB konfigurace musí vzniknout v globálním scope dříve, než app.php zpřístupní db().
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../lib/mereni_vykonu.php';
require_once __DIR__ . '/../db/db_ajax_trace.php';

/**
 * Ukončí neplatný požadavek čitelnou JSON chybou a odpovídajícím HTTP stavem.
 */
function cb_ajax_trace_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    cb_ajax_trace_error(405, 'Endpoint přijímá pouze POST požadavky.');
}

if (empty($_SESSION['login_ok']) || !cb_session_validate_after_login()) {
    cb_ajax_trace_error(401, 'Přihlášená session není platná.');
}

cb_crf_vyzaduj();

$raw = file_get_contents('php://input');
if (!is_string($raw) || trim($raw) === '') {
    cb_ajax_trace_error(400, 'Chybí JSON obsah diagnostické události.');
}

try {
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    cb_ajax_trace_error(400, 'JSON diagnostické události není platný.');
}

if (!is_array($data)) {
    cb_ajax_trace_error(400, 'Diagnostická událost musí být JSON objekt.');
}

$event = trim((string)($data['event'] ?? ''));
if ($event === '') {
    cb_ajax_trace_error(400, 'Diagnostická událost nemá název.');
}

try {
    $conn = db();
    $traceEnabled = cb_db_ajax_trace_enabled($conn);

    // Srovná session cache se skutečnou globální hodnotou a případně dodatečně
    // zapne jednotný request logger, který při ukončení požadavku uloží detail.
    if (!isset($_SESSION['cb_system']) || !is_array($_SESSION['cb_system'])) {
        $_SESSION['cb_system'] = cb_system_settings_defaults();
    }
    $_SESSION['cb_system']['log_3'] = $traceEnabled ? 1 : 0;

    if (!$traceEnabled) {
        http_response_code(204);
        exit;
    }

    cb_db_akce_log_init($conn);

    $traceData = is_array($data['data'] ?? null) ? $data['data'] : [];
    $traceTotalMs = null;
    if (isset($traceData['total_ms']) && is_numeric($traceData['total_ms'])) {
        $traceTotalMs = (float)$traceData['total_ms'];
    } elseif (isset($traceData['nav']) && is_array($traceData['nav'])) {
        if (isset($traceData['nav']['total_ms']) && is_numeric($traceData['nav']['total_ms'])) {
            $traceTotalMs = (float)$traceData['nav']['total_ms'];
        } elseif (isset($traceData['nav']['load_event_ms']) && is_numeric($traceData['nav']['load_event_ms'])) {
            $traceTotalMs = (float)$traceData['nav']['load_event_ms'];
        }
    }

    $filterPob = [];
    if (isset($_SESSION['selected_pobocky']) && is_array($_SESSION['selected_pobocky'])) {
        $filterPob = $_SESSION['selected_pobocky'];
    } elseif (isset($_SESSION['cb_pobocka_id'])) {
        $filterPob = [(int)$_SESSION['cb_pobocka_id']];
    }
    $filterPob = array_values(array_filter(array_map('intval', $filterPob), static fn (int $id): bool => $id > 0));

    cb_tmp_measure_detail_add([
        'typ' => 'ajax',
        'nazev' => $event,
        'total_ms' => $traceTotalMs,
        'detail' => [
            'sid' => session_id(),
            'uid' => (int)($_SESSION['cb_user']['id_user'] ?? 0),
            'href' => (string)($data['href'] ?? ''),
            'path' => (string)($data['path'] ?? ''),
            'data' => $traceData,
            'filter_od' => trim((string)($_SESSION['cb_obdobi_od'] ?? '')),
            'filter_do' => trim((string)($_SESSION['cb_obdobi_do'] ?? '')),
            'filter_pob' => $filterPob,
            'filter_mode' => trim((string)($_SESSION['selected_pobocky_mode'] ?? '')),
        ],
    ]);
} catch (Throwable $e) {
    error_log('ajax_trace: ' . $e->getMessage());
    cb_ajax_trace_error(500, 'Diagnostickou událost se nepodařilo uložit.');
}

http_response_code(204);
