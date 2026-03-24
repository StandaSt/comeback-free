<?php
// index.php * Verze: V22 * Aktualizace: 06.03.2026

/*
 * FRONT CONTROLLER (centralni vstup aplikace)
 *
 * Co dela:
 * - startuje session + nacita app/system/secrets
 * - FULL load: zobrazi vychozi stranku podle prihlaseni
 * - AJAX (partial): vraci jen obsah do <main> podle sekce
 * - 404: vrati hlasku a zapise zaznam do DB tabulky chyba
 *
 * Zavislosti:
 * - lib/app.php, lib/system.php, config/secrets.php
 * - lib/nacti_styly.php
 * - includes/hlavicka.php, includes/main.php, includes/paticka.php, includes/dashboard.php
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/system.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/config/secrets.php';

require_once __DIR__ . '/lib/detektuj_neplatnou_url.php';

require_once __DIR__ . '/lib/logout_handler.php';

require_once __DIR__ . '/lib/json_registrace.php';

require_once __DIR__ . '/lib/post_akce.php';

/* =========================
   1) AJAX (partial) rezim
   ========================= */
$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
}

/* =========================
   2) Volba sekce dashboardu
   ========================= */
$sekceRaw = '';
if (isset($_GET['sekce'])) {
    $sekceRaw = (string)$_GET['sekce'];
} elseif (isset($_SERVER['HTTP_X_COMEBACK_SEKCE'])) {
    $sekceRaw = (string)$_SERVER['HTTP_X_COMEBACK_SEKCE'];
} elseif (isset($_SERVER['HTTP_X_COMEBACK_PAGE'])) {
    // Kompatibilita se starsim JS (home/manager/admin).
    $legacy = (string)$_SERVER['HTTP_X_COMEBACK_PAGE'];
    if ($legacy === 'home') {
        $sekceRaw = '3';
    } elseif ($legacy === 'manager') {
        $sekceRaw = '2';
    } elseif ($legacy === 'admin' || $legacy === 'admin_dashboard') {
        $sekceRaw = '1';
    }
}

$cbSekce = (int)$sekceRaw;
if (!in_array($cbSekce, [1, 2, 3], true)) {
    $cbSekce = 3;
}

$cbUser = $_SESSION['cb_user'] ?? [];
$cbUserRoleId = (int)($cbUser['id_role'] ?? 9);
if ($cbUserRoleId <= 0) {
    $cbUserRoleId = 9;
}

// Bezpecnost: pokud uzivatel vyzada sekci, na kterou nema pravo, vraci se na home (3).
if ($cbSekce < 3 && $cbUserRoleId > $cbSekce) {
    $cbSekce = 3;
}

$cb_dashboard_sekce = $cbSekce;
$pageKey = 'dashboard';
$file = __DIR__ . '/includes/dashboard.php';

/* =========================
   3) Render / 404 + log
   ========================= */
require_once __DIR__ . '/includes/log_a_404.php';

/* =========================
   4) AJAX (partial): jen obsah stranky
   ========================= */
if ($cbIsPartial) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo '<section class="card"><p>Nutne prihlaseni.</p></section>';
        exit;
    }

    if ($cbPageExists) {
        require $file;
    } else {
        echo '<div class="page-head"><h2>Stranka nenalezena</h2></div>';
        echo '<section class="card"><p>Pozadovana stranka neexistuje.</p></section>';
    }
    exit;
}

/* =========================
   5) FULL render: layout + stranka
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

/*
 * Neprihlaseny stav:
 * - login modal
 * - nebo cekani na 2FA po zadani hesla
 */
require_once __DIR__ . '/modaly/modal_overeni.php';

/*
 * Prihlaseny bez sparovaneho mobilu uvidi modal parovani.
 */
require_once __DIR__ . '/lib/kontrola_registrace.php';

$cb_page_exists = $cbPageExists;
$cb_page_file = $file;

require_once __DIR__ . '/includes/main.php';

require_once __DIR__ . '/includes/paticka.php';

if (!function_exists('cb_asset_url')) {
    function cb_asset_url(string $path): string
    {
        $full = __DIR__ . '/' . ltrim($path, '/');
        $ver = is_file($full) ? (string)filemtime($full) : '1';
        return cb_url($path) . '?v=' . $ver;
    }
}

?>
</div>


<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/menu_core.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/menu_ajax.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_hlavicka.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_restia.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_form.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_person.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/admin_karty.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/casovac_odhlaseni.js')) ?>"></script>



<?php
if (!empty($cbInvalidUrl)) {
    $cbUserForAlert = $_SESSION['cb_user'] ?? [];
    $cbUserName = trim((string)($cbUserForAlert['name'] ?? ''));
    $cbUserSurname = trim((string)($cbUserForAlert['surname'] ?? ''));
    $cbAlertUserName = trim($cbUserName . ' ' . $cbUserSurname);
    if ($cbAlertUserName === '') {
        $cbAlertUserName = 'Neznámý uživatel';
    }

$fullRequestUrl =
    ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://'
    . (string)($_SERVER['HTTP_HOST'] ?? 'localhost')
    . (string)($_SERVER['REQUEST_URI'] ?? '/');

    $cbAlertInvalidUrl = $fullRequestUrl;

    require_once __DIR__ . '/modaly/modal_alert_url.php';
}
?>
</body>
</html>
<?php
/* index.php * Verze: V22 * Aktualizace: 06.03.2026 */
// Konec souboru
