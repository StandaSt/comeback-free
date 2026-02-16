<?php
// index.php * Verze: V9 * Aktualizace: 14.2.2026 
declare(strict_types=1);

/*
 * FRONT CONTROLLER (centrální vstup aplikace)
 *
 * Účel:
 * - načíst bootstrap (start projektu)
 * - FULL load: vždy zobrazí "uvod" (URL se nemění, page se nebere z URL)
 * - AJAX (partial): vrací jen obsah do <main> podle hlavičky X-Comeback-Page
 * - přepnutí menu režimu: uloží do session přes POST + X-Comeback-Set-Menu
 */

require_once __DIR__ . '/lib/bootstrap.php';

/* =========================
   0) Nastavení menu do session (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_MENU'])
) {
    $m = (string)$_SERVER['HTTP_X_COMEBACK_SET_MENU'];
    $m = ($m === 'sidebar') ? 'sidebar' : 'dropdown';
    $_SESSION['cb_menu_mode'] = $m;

    http_response_code(204);
    exit;
}

/* =========================
   1) AJAX (partial) režim
   ========================= */
$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)$_SERVER['HTTP_X_COMEBACK_PARTIAL'] === '1');
}

// FULL render (běžné načtení stránky)
if (!$cbIsPartial) {
    require_once __DIR__ . '/includes/hlavicka.php';
}

/* =========================
   2) Volba stránky
   ========================= */
// FULL load: vždy uvod (URL se nemění a page z URL ignorujeme)
$pageKey = 'uvod';

// AJAX: stránka se bere jen z hlavičky X-Comeback-Page
if ($cbIsPartial) {
    $pageKey = (string)($_SERVER['HTTP_X_COMEBACK_PAGE'] ?? 'uvod');
}

// Očištění page na povolené znaky: a–z, 0–9, podtržítko
$pageKey = preg_replace('~[^a-z0-9_]+~i', '', $pageKey) ?: 'uvod';

// Sestavení cesty k souboru stránky v /pages
$file = __DIR__ . '/pages/' . $pageKey . '.php';

/* =========================
   3) Render / 404 + log
   ========================= */
if (!is_file($file)) {
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
        // Logování 404 nikdy nesmí shodit stránku.
    }

    echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
    echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
} else {
    require $file;
}

// FULL render (běžné načtení stránky)
if (!$cbIsPartial) {
    require_once __DIR__ . '/includes/paticka.php';
}

/* index.php * Verze: V9 * Aktualizace: 14.2.2026 * Počet řádků: 142 */
// Konec souboru