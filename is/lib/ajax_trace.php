<?php
/*
 * Debug endpoint pro AJAX a loader udalosti.
 * Pri zapnutem log_3 zapisuje udalosti do DB detailu requestu.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../www/lib/session_boot.php';

require_once __DIR__ . '/../../www/lib/app.php';
require_once __DIR__ . '/mereni_vykonu.php';

$raw = file_get_contents('php://input');
$data = [];

if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if ($data === [] && $_POST !== []) {
    $data = $_POST;
}

$event = trim((string)($data['event'] ?? ''));
if ($event === '') {
    http_response_code(204);
    exit;
}

$ts = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
$sid = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
$user = '';
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user'])) {
    $user = (string)($_SESSION['cb_user']['id_user'] ?? '');
}

$filterOd = '';
$filterDo = '';
$filterPob = [];
$filterMode = '';
if (session_status() === PHP_SESSION_ACTIVE) {
    $filterOd = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
    $filterDo = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));
    if (isset($_SESSION['selected_pobocky']) && is_array($_SESSION['selected_pobocky'])) {
        $filterPob = $_SESSION['selected_pobocky'];
    } elseif (isset($_SESSION['cb_pobocka_id'])) {
        $filterPob = [(int)$_SESSION['cb_pobocka_id']];
    }
    $filterPob = array_values(array_filter(array_map('intval', $filterPob), static fn (int $v): bool => $v > 0));
    $filterMode = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
}

$ajaxTraceEnabled = function_exists('cb_system_setting') && (int)cb_system_setting('log_3', 0) === 1;
if (!$ajaxTraceEnabled) {
    http_response_code(204);
    exit;
}

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

if (function_exists('cb_tmp_measure_detail_add')) {
    cb_tmp_measure_detail_add([
        'typ' => 'ajax',
        'nazev' => $event,
        'total_ms' => $traceTotalMs,
        'detail' => [
            'sid' => $sid,
            'uid' => $user,
            'href' => (string)($data['href'] ?? ''),
            'path' => (string)($data['path'] ?? ''),
            'data' => $traceData,
            'filter_od' => $filterOd,
            'filter_do' => $filterDo,
            'filter_pob' => $filterPob,
            'filter_mode' => $filterMode,
        ],
    ]);
}

try {
    db();
} catch (Throwable $e) {
    // Diagnostika nesmi rozbit bezny request.
}

http_response_code(204);
