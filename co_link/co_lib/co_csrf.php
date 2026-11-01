<?php
/* Co-Link: overi vlastni CSRF token Co-Linku pred zapisovou akci. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';

/** Zapisove akce potrebuji vlastni CSRF token; QR vymena pouziva tajny jednorazovy token. */
function co_csrf(array $identity): void
{
    $v = (string)($_SERVER['HTTP_X_CO_CSRF'] ?? '');
    if ($v === '' || !hash_equals((string)$identity['csrf'], $v)) co_chyba('Neplatné ověření požadavku. Obnovte Co-Link.',403);
}
