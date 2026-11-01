<?php
/* Co-Link: overi text a formatovani upravovane zpravy a povoli prazdny text jen pri existenci prilohy. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_message_format.php';
require_once __DIR__ . '/../co_db/co_zprava_prilohu_overit.php';
require_once __DIR__ . '/../co_db/co_zpravu_upravit.php';

/** Volajici uz zamkl zpravu a overil pravo na zmenu; tato cast resi pouze novy obsah. */
function co_zprava_uprava(int $id, string $raw): void
{
    $text = co_zprava_text($raw);
    if ($text === '' && !co_zprava_prilohu_overit($id)) co_chyba('Zpráva nesmí být prázdná ani delší než 20 000 znaků.');
    co_zpravu_upravit($id, $text);
}
