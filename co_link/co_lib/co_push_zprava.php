<?php
/* Co-Link: pod zamkem koordinuje prijemce a rozeslani jedne ulozene zpravy. */
declare(strict_types=1);
require_once __DIR__ . '/co_push_priprava.php';
require_once __DIR__ . '/co_push_prijemce_overit.php';
require_once __DIR__ . '/co_push_odeslat.php';
require_once __DIR__ . '/../co_db/co_push_zamek_ziskat.php';
require_once __DIR__ . '/../co_db/co_push_zamek_uvolnit.php';
require_once __DIR__ . '/../co_db/co_push_prijemce_nacist.php';
require_once __DIR__ . '/../co_db/co_push_pokus_preskocit.php';

/** Zamek brani soubeznemu odeslani stejne zpravy z API a davky; uvolni se i pri chybe. */
function co_push_zprava(string $baseUrl, int $message): int
{
    if (!co_push_priprava()) return 0;
    $lockName = 'co-link-push-' . $message;
    if (!co_push_zamek_ziskat($lockName)) return 0;
    try {
        $rows = co_push_prijemce_nacist($message);
        foreach ($rows as $r) {
            if (!co_push_prijemce_overit($r)) {
                co_push_pokus_preskocit((int)$r['id']);
                continue;
            }
            co_push_odeslat($baseUrl, $r);
        }
        // Puvodni navratova hodnota pocita vsechny nactene zaznamy vcetne preskocenych.
        return count($rows);
    } finally {
        co_push_zamek_uvolnit($lockName);
    }
}
