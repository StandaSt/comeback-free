<?php
// includes/log_a_404.php * Verze: V1 * Aktualizace: 06.03.2026
declare(strict_types=1);

/*
 * 404 + log chyby
 *
 * Vstup z index.php:
 * - $file
 * - $pageKey
 *
 * Výstup:
 * - nastaví $cbPageExists
 * - při 404 zkusí zapsat záznam do DB tabulky chyba
 */

$cbPageExists = is_file($file);

if (!$cbPageExists) {
    http_response_code(404);

    try {
        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : null;

        $prostredi = (string)($GLOBALS['PROSTREDI'] ?? '');
        if ($prostredi === '') {
            $prostredi = 'UNKNOWN';
        }

        $url = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($url === '') {
            $url = 'UNKNOWN';
        }

        $metoda = (string)($_SERVER['REQUEST_METHOD'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        $oblast = 'HTTP';
        $kod = '404';
        $zprava = 'Stránka nenalezena';

        $detail = json_encode([
            'pageKey' => $pageKey,
            'file' => $file,
        ], JSON_UNESCAPED_UNICODE);

        $conn = db();

        $stmt = $conn->prepare('\n            INSERT INTO chyba\n            (prostredi, url, page, metoda, ip, user_agent, id_user, zavaznost, oblast, kod, zprava, detail)\n            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)\n        ');

        if ($stmt) {
            $zavaznost = 2;

            $stmt->bind_param(
                'ssssssiissss',
                $prostredi,
                $url,
                $pageKey,
                $metoda,
                $ip,
                $ua,
                $idUser,
                $zavaznost,
                $oblast,
                $kod,
                $zprava,
                $detail
            );

            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Logování 404 nikdy nesmí shodit stránku.
    }
}
