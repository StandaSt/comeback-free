<?php
/* Co-Link: overi klientsky identifikator, text a seznam priloh a pripravi vstup odesilani. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_message_format.php';

/** Zachovava puvodni poradi validace a nejvyse 20 ruznych priloh. */
function co_zprava_vstup(array $d): array
{
    $client = (string)($d['klient_id'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9-]{20,36}$/D', $client)) co_chyba('Chybí identifikátor zprávy.');
    $text = co_zprava_text((string)($d['text'] ?? ''));
    $files = array_values(array_unique(array_map('intval', (array)($d['prilohy'] ?? []))));
    if (count($files) > 20 || ($text === '' && $files === [])) co_chyba('Zadejte zprávu do 20 000 znaků nebo nejvýše 20 příloh.');
    return ['client' => $client, 'text' => $text, 'files' => $files, 'cid' => (int)($d['konverzace'] ?? 0)];
}
