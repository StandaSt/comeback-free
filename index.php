<?php
// index.php * Verze: V8 * Aktualizace: 13.2.2026 * Počet řádků: 128
declare(strict_types=1);

/*
 * FRONT CONTROLLER (centrální vstup aplikace)
 *
 * Účel:
 * - načíst bootstrap (start projektu)
 * - vykreslit hlavičku (otevře HTML + layout)
 * - načíst konkrétní stránku z /pages podle parametru ?page=
 * - když stránka neexistuje, vrátit 404 + zapsat do DB tabulky chyba (pokud půjde)
 * - vykreslit patičku (uzavře layout + HTML)
 *
 * Poznámka k bezpečnosti:
 * - NEPOUŽÍVÁ se whitelist (seznam povolených stránek).
 * - Bezpečnost je řešená tím, že page se čistí na [a-z0-9_],
 *   takže nejde použít znaky jako ../ pro únik z adresáře /pages.
 *
 * Nově (V8):
 * - Podpora AJAX režimu pro načítání jen obsahu do <main> (bez hlavičky/patičky).
 */

require_once __DIR__ . '/lib/bootstrap.php';

// AJAX (partial) režim: když je hlavička X-Comeback-Partial: 1
$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)$_SERVER['HTTP_X_COMEBACK_PARTIAL'] === '1');
}

// FULL render (běžné načtení stránky)
if (!$cbIsPartial) {
    require_once __DIR__ . '/includes/hlavicka.php';
}

// Parametr page z URL:
// - když chybí, použije se "uvod"
$pageKey = (string)($_GET['page'] ?? 'uvod');

// Očištění page na povolené znaky:
// - povoleno: a–z, 0–9, podtržítko
// - všechno ostatní se odstraní
// - když po vyčištění zbyde prázdno, vrátí se "uvod"
$pageKey = preg_replace('~[^a-z0-9_]+~i', '', $pageKey) ?: 'uvod';

// Sestavení cesty k souboru stránky v /pages
$file = __DIR__ . '/pages/' . $pageKey . '.php';

// Když soubor neexistuje → 404 + pokus o log do DB (nesmí shodit stránku)
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

/* index.php * Verze: V8 * Aktualizace: 13.2.2026 * Počet řádků: 128 */
// Konec souboru