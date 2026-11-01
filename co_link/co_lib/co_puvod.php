<?php
/* Co-Link: overi stejny puvod pozadavku i pri QR vymene bez session. */
declare(strict_types=1);
require_once __DIR__ . '/co_https.php';
require_once __DIR__ . '/co_chyba.php';

/** Povoli zapis pouze ze stejneho webu, vcetne QR vymeny bez existujici session. */
function co_puvod(): void
{
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $expected = (co_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
    if ($origin !== $expected) co_chyba('Požadavek nepochází z Co-Linku.',403);
}
