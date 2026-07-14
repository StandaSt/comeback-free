<?php
// includes/log_a_404.php * Verze: V2 * Aktualizace: 24.03.2026
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
 * - při 404 zapíše záznam přes db_zapis_log_chyby()
 */

require_once __DIR__ . '/../db/zapis_log_chyby.php';

$file = $file ?? '';
$pageKey = $pageKey ?? '';

$cbPageExists = is_file($file);

if (!$cbPageExists) {
    http_response_code(404);

    try {
        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : null;

        $url = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($url === '') {
            $url = null;
        }

        db_zapis_log_chyby(
            conn: db(),
            idUser: $idUser,
            modul: 'HTTP',
            akce: '404',
            kod: 'PAGE_NOT_FOUND',
            zprava: 'Stránka nenalezena',
            detail: json_encode([
                'pageKey' => $pageKey,
                'file' => $file,
            ], JSON_UNESCAPED_UNICODE),
            soubor: __FILE__,
            radek: __LINE__,
            url: $url,
            dataJson: null,
            vyreseno: 0,
            poznamka: null
        );

    } catch (Throwable $e) {
        // logování nesmí shodit aplikaci
    }
}
