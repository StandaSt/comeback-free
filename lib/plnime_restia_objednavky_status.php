<?php
/*
 * Stavový endpoint pro loader Restia importu objednávek.
 * Čte průběžný stav z lib/plnime_restia_objednavky_status.json
 * a vrací ho jako JSON pro polling v UI.
 */

declare(strict_types=1);

$statusPath = __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$payload = [
    '_comment' => 'Průběžný stav Restia importu objednávek pro loader.',
    'ok' => true,
    'active' => 0,
    'saved_step' => 0,
    'saved_total' => 0,
];

if (is_file($statusPath)) {
    $raw = @file_get_contents($statusPath);
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = array_merge($payload, $decoded);
            $payload['ok'] = true;
        }
    }
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
