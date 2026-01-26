<?php
// index.php V6 – počet řádků: 87
declare(strict_types=1);

/*
 * Front controller
 * - BEZ whitelistu (žádné comeback_pages())
 * - načítá hlavičku, stránku, patičku
 * - loguje 404 do tabulky chyba
 *
 * Konvence:
 * - stránky jsou v /pages a jmenují se <page>.php
 * - page parametr se čistí na [a-z0-9_]
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/hlavicka.php';

$pageKey = (string)($_GET['page'] ?? 'uvod');
$pageKey = preg_replace('~[^a-z0-9_]+~i', '', $pageKey) ?: 'uvod';

$file = __DIR__ . '/pages/' . $pageKey . '.php';

if (!is_file($file)) {
    http_response_code(404);

    try {
        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : null;

        $prostredi = (string)($GLOBALS['PROSTREDI'] ?? '');
        if ($prostredi === '') $prostredi = 'UNKNOWN';

        $url = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($url === '') $url = 'UNKNOWN';

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
        $stmt = $conn->prepare('
            INSERT INTO chyba
            (prostredi, url, page, metoda, ip, user_agent, id_user, zavaznost, oblast, kod, zprava, detail)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ');
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
        // logování 404 nikdy nesmí shodit stránku
    }

    echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
    echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
} else {
    require $file;
}

require_once __DIR__ . '/includes/paticka.php';

/* index.php V6 – počet řádků: 87 */
