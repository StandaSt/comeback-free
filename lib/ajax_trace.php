<?php
/*
 * Debug endpoint pro zapis AJAX a loader udalosti.
 * Prijima kratke JSON zpravy z prohlizece a zapisuje je do log/ajax_trace.log,
 * aby bylo videt, co presne dela dashboard, cards a Restia loader.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$logPath = __DIR__ . '/../log/ajax_trace.log';
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

if (!is_dir(dirname($logPath))) {
    @mkdir(dirname($logPath), 0777, true);
}

$ts = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
$sid = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
$user = '';
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user'])) {
    $user = (string)($_SESSION['cb_user']['id_user'] ?? '');
}

if ((int)$user !== 1) {
    http_response_code(204);
    exit;
}

$line = [
    $ts,
    'event=' . $event,
    'sid=' . $sid,
    'uid=' . $user,
    'href=' . (string)($data['href'] ?? ''),
    'path=' . (string)($data['path'] ?? ''),
    'data=' . json_encode($data['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
];

@file_put_contents($logPath, implode(' | ', $line) . "\n", FILE_APPEND | LOCK_EX);

if (str_starts_with($event, 'measure_')) {
    $measureDir = __DIR__ . '/../log';
    $measurePathAi = $measureDir . '/merime_casy_AI.txt';
    $measurePathUser = $measureDir . '/merime_casy_user.txt';
    if (!is_dir($measureDir)) {
        @mkdir($measureDir, 0777, true);
    }

    $measureData = is_array($data['data'] ?? null) ? $data['data'] : [];
    $measureNav = is_array($measureData['nav'] ?? null) ? $measureData['nav'] : [];
    $measureTotal = '';
    if (isset($measureData['total_ms'])) {
        $measureTotal = (string)((int)$measureData['total_ms']);
    } elseif (isset($measureNav['total_ms'])) {
        $measureTotal = (string)((int)$measureNav['total_ms']);
    } elseif (isset($measureNav['load_event_ms'])) {
        $measureTotal = (string)((int)$measureNav['load_event_ms']);
    }

    $measureLines = [];
    $measureLines[] = $ts . ' | client | ' . $event . ($measureTotal !== '' ? ' / total_ms=' . $measureTotal : '');
    $measureLines[] = '  sid=' . $sid . ' | uid=' . $user . ' | path=' . (string)($data['path'] ?? '');
    $measureLines[] = '  href=' . (string)($data['href'] ?? '');

    if ($measureNav !== []) {
        $navStage = (string)($measureNav['stage'] ?? '');
        $navType = (string)($measureNav['nav_type'] ?? '');
        $isReload = (string)($measureNav['is_reload'] ?? '');
        $responseEnd = (string)($measureNav['response_end_ms'] ?? '');
        $domContent = (string)($measureNav['dom_content_loaded_ms'] ?? '');
        $loadEvent = (string)($measureNav['load_event_ms'] ?? '');
        $domComplete = (string)($measureNav['dom_complete_ms'] ?? '');
        $transferSize = (string)($measureNav['transfer_size'] ?? '');
        $encodedBody = (string)($measureNav['encoded_body_size'] ?? '');
        $decodedBody = (string)($measureNav['decoded_body_size'] ?? '');

        $measureLines[] = '  nav=' . $navStage . ' | nav_type=' . $navType . ' | is_reload=' . $isReload;
        $measureLines[] = '  response_end_ms=' . $responseEnd . ' | dom_content_loaded_ms=' . $domContent . ' | load_event_ms=' . $loadEvent . ' | dom_complete_ms=' . $domComplete;
        $measureLines[] = '  transfer_size=' . $transferSize . ' | encoded_body_size=' . $encodedBody . ' | decoded_body_size=' . $decodedBody;
    }

    if (isset($measureData['slow_resources']) && is_array($measureData['slow_resources']) && $measureData['slow_resources'] !== []) {
        $measureLines[] = '  slow_resources:';
        foreach ($measureData['slow_resources'] as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $measureLines[] = '    - ' . (string)($resource['name'] ?? '') . ' | ' . (string)($resource['duration_ms'] ?? '') . ' ms';
        }
    }

    $measureLines[] = '';

    $measureUserLine = $ts . ' | client | ' . $event . ($measureTotal !== '' ? ' / total_ms=' . $measureTotal : '');

    @file_put_contents($measurePathUser, $measureUserLine . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($measurePathAi, implode("\n", $measureLines) . "\n", FILE_APPEND | LOCK_EX);
}

http_response_code(204);
