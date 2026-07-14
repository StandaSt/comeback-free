<?php
// lib/restia_access_exist.php * Verze: V2 * Aktualizace: 10.03.2026
declare(strict_types=1);

/*
 * RESTIA - kontrola platnosti access tokenu
 *
 * Ucel:
 * - sahne do DB (restia_token id=1)
 * - pokud je access_token platny -> konec
 * - pokud neni -> require restia_ziskej_access.php
 */

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
                    return; // token je platny -> koncime
                }
            } catch (Throwable $e) {
                // poskozeny cas -> ziskame novy token
            }
        }
    }
}

// pokud jsme dosli sem -> token neni platny
require __DIR__ . '/restia_ziskej_access.php';

// Konec souboru
