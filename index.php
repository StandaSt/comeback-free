<?php
// index.php * Verze: V16 * Aktualizace: 26.2.2026

/*
 * FRONT CONTROLLER (centrální vstup aplikace)
 *
 * Co dělá:
 * - načte bootstrap (start projektu, session, helpery, db())
 * - FULL load: vždy zobrazí výchozí stránku podle přihlášení (nastavení v lib/system.php)
 * - AJAX (partial): vrací jen obsah do <main> podle hlavičky X-Comeback-Page (bez layoutu)
 * - přepnutí menu režimu: uloží do session přes POST + X-Comeback-Set-Menu
 * - 404: vrátí hlášku a pokusí se zapsat záznam do DB tabulky `chyba`
 * - V12: nepřihlášený uvidí jen hlavičku + modální přihlášení (includes/login_modal.php)
 * - V15: prvni_login se zobrazuje jako MODÁL (includes/prvni_login.php), stejně jako login modal
 * - V16: kontrola spárování mobilu je jen existence aktivního řádku v push_zarizeni; pokud chybí id_user v session, vynutí prvni_login
 *
 * Volá / závisí na:
 * - lib/bootstrap.php
 * - lib/nacti_styly.php
 * - includes/hlavicka.php
 * - includes/central.php
 * - includes/paticka.php
 * - pages/<pageKey>.php
 *
 * Requestuje / čte:
 * - HTTP hlavičky: X-Comeback-Set-Menu, X-Comeback-Partial, X-Comeback-Page
 * - session: login_ok (volba výchozí stránky), cb_menu_mode, cb_user[id_user] (pro log 404)
 */

declare(strict_types=1);

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
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
}

/* =========================
   2) Volba stránky
   ========================= */
/*
 * Výchozí stránka je definovaná v lib/system.php:
 * - CB_DEFAULT_PAGE_GUEST (nepřihlášený)
 * - CB_DEFAULT_PAGE_USER  (přihlášený)
 *
 * Pozn.: když by system.php ještě nebyl načtený, spadne to na 'uvod'.
 */
$defaultGuest = defined('CB_DEFAULT_PAGE_GUEST') ? (string)CB_DEFAULT_PAGE_GUEST : 'uvod';
$defaultUser  = defined('CB_DEFAULT_PAGE_USER')  ? (string)CB_DEFAULT_PAGE_USER  : 'uvod';
$defaultPage  = (!empty($_SESSION['login_ok'])) ? $defaultUser : $defaultGuest;

// FULL load: vždy výchozí stránka (URL se nemění a page z URL ignorujeme)
$pageKey = $defaultPage;

// AJAX: stránka se bere jen z hlavičky X-Comeback-Page (fallback = výchozí stránka)
if ($cbIsPartial) {
    $pageKey = (string)($_SERVER['HTTP_X_COMEBACK_PAGE'] ?? $defaultPage);
}

// Očištění page na povolené znaky: a–z, 0–9, podtržítko
$pageKey = preg_replace('~[^a-z0-9_]+~i', '', $pageKey) ?: $defaultPage;

// Sestavení cesty k souboru stránky v /pages
$file = __DIR__ . '/pages/' . $pageKey . '.php';

/* =========================
   3) Render / 404 + log
   ========================= */
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
}

/* =========================
   4) AJAX (partial): jen obsah stránky
   ========================= */
if ($cbIsPartial) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo '<section class="card"><p>Nutné přihlášení.</p></section>';
        exit;
    }

    if ($cbPageExists) {
        require $file;
    } else {
        echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
        echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
    }
    exit;
}

/* =========================
   5) FULL render: layout + stránka
   ========================= */
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comeback</title>

    <?php require_once __DIR__ . '/lib/nacti_styly.php'; ?>
</head>
<body>

<div class="container">
<?php

require_once __DIR__ . '/includes/hlavicka.php';

if (empty($_SESSION['login_ok'])) {

    echo '<div class="cb-login-fill"></div>';

    require_once __DIR__ . '/includes/login_modal.php';

    ?>
</div>
</body>
</html>
<?php
    exit;
}

/*
 * V15+V16: přihlášený bez spárovaného mobilu uvidí prvni_login (MODÁL)
 *
 * - kontrola: existuje aktivní záznam v push_zarizeni pro id_user
 * - pokud chybí id_user v session, prvni_login se vynutí (bez id_user nedokážeme spárování ověřit)
 */
$cbUser = $_SESSION['cb_user'] ?? null;
$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

$maMobil = false;

if ($idUser > 0) {

    $conn = db();

    $stmt = $conn->prepare('
        SELECT id
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        LIMIT 1
    ');

    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->store_result();
        $maMobil = ($stmt->num_rows > 0);
        $stmt->close();
    }
}

if (!$maMobil) {

    echo '<div class="cb-login-fill"></div>';

    require_once __DIR__ . '/includes/prvni_login.php';

    ?>
</div>
</body>
</html>
<?php
    exit;
}

$cb_page_exists = $cbPageExists;
$cb_page_file = $file;

require_once __DIR__ . '/includes/central.php';

require_once __DIR__ . '/includes/paticka.php';

?>
</div>

<script src="<?= h(cb_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_url('js/menu_ajax.js')) ?>"></script>
<script src="<?= h(cb_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_url('js/filtry_reset.js')) ?>"></script>
<script src="<?= h(cb_url('js/strankovani.js')) ?>"></script>
<script src="<?= h(cb_url('js/casovac_odhlaseni.js')) ?>"></script>

</body>
</html>
<?php
/* index.php * Verze: V16 * Aktualizace: 26.2.2026 * Počet řádků: 275 */
// Konec souboru