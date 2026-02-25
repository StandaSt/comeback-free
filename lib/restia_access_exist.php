<?php
// lib/restia_access_exist.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 57
declare(strict_types=1);

/*
 * RESTIA – kontrola platnosti access tokenu
 *
 * Účel:
 * - sáhne do DB (restia_token id=1)
 * - pokud je access_token platný → konec
 * - pokud není → require restia_ziskej_access.php
 *
 * NIC VÍC.
 */

require_once __DIR__ . '/bootstrap.php';

$conn = db();

$sql = 'SELECT access_token, expires_at
        FROM restia_token
        WHERE id_restia_token = 1
        LIMIT 1';

$res = $conn->query($sql);

if ($res !== false) {

    $row = $res->fetch_assoc();

    if (is_array($row)) {

        $token  = (string)($row['access_token'] ?? '');
        $expStr = (string)($row['expires_at'] ?? '');

        if ($token !== '' && $expStr !== '') {

            try {
                $exp = new DateTimeImmutable($expStr, new DateTimeZone('UTC'));
                $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

                if ($exp > $now->modify('+60 seconds')) {
                    return; // token je platný → končíme
                }

            } catch (Throwable $e) {
                // poškozený čas → získáme nový token
            }
        }
    }
}

// pokud jsme došli sem → token není platný
require __DIR__ . '/restia_ziskej_access.php';

// lib/restia_access_exist.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 57
// Konec souboru