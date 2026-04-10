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

http_response_code(204);
