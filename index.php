<?php
// index.php * Verze: V7 * Aktualizace: 4.2.2026 * Počet řádků: 128
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
 */

    // start projektu (session, helpery, db())
require_once __DIR__ . '/lib/bootstrap.php'; 

require_once __DIR__ . '/includes/hlavicka.php'; // otevře HTML/layout, vykreslí hlavičku a menu

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
    http_response_code(404); // správný HTTP kód pro prohlížeč, logy i monitoring

    try {
        // V SESSION se (pokud je uživatel přihlášen) očekává $_SESSION['cb_user']
        $cbUser = $_SESSION['cb_user'] ?? null;

        // id_user je volitelný (když uživatel není přihlášený, bude NULL)
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : null;

        // Prostředí aplikace (např. DEV/PROD) – v projektu je to globál $PROSTREDI
        $prostredi = (string)($GLOBALS['PROSTREDI'] ?? '');
        if ($prostredi === '') $prostredi = 'UNKNOWN';

        // Aktuální URL (celá request URI)
        $url = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($url === '') $url = 'UNKNOWN';

        // HTTP metoda (GET/POST/...)
        $metoda = (string)($_SERVER['REQUEST_METHOD'] ?? '');

        // IP adresa + user agent (identifikace prohlížeče)
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Kategorizace chyby pro tabulku chyba
        $oblast = 'HTTP';
        $kod = '404';
        $zprava = 'Stránka nenalezena';

        // Detail do JSON:
        // - strojově čitelné info, které pomůže dohledat, co přesně chybělo
        $detail = json_encode([
            'pageKey' => $pageKey,
            'file' => $file,
        ], JSON_UNESCAPED_UNICODE);

        // Připojení do DB přes jediný oficiální vstup db() (helper z bootstrapu)
        $conn = db();

        // Prepared statement (připravený dotaz) – chrání proti SQL injection (podstrčení SQL)
        $stmt = $conn->prepare('
            INSERT INTO chyba
            (prostredi, url, page, metoda, ip, user_agent, id_user, zavaznost, oblast, kod, zprava, detail)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ');

        // Když prepare selže, $stmt bude false – v tom případě log přeskočíme
        if ($stmt) {
            $zavaznost = 2;

            // bind_param typy:
            // s = string, i = int
            // pořadí musí sedět s VALUES(...)
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

            // Provedení insertu + uvolnění statementu
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Logování 404 nikdy nesmí shodit stránku (DB může být dole, může chybět tabulka, atd.)
        // Záměrně se chyba ignoruje a pokračuje se uživatelským výstupem 404.
    }

    // Uživatelský výstup 404 (jednoduché HTML)
    echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
    echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
} else {
    // Soubor existuje → načte se a vykreslí obsah stránky
    require $file;
}

// Technické ukončení layoutu + HTML (paticka.php v /includes)
require_once __DIR__ . '/includes/paticka.php';

// index.php * Verze: V7 * Aktualizace: 4.2.2026 * Počet řádků: 128
