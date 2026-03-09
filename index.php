<?php
// index.php * Verze: V22 * Aktualizace: 06.03.2026

/*
 * FRONT CONTROLLER (centrální vstup aplikace)
 *
 * Co dělá:
 * - načte bootstrap (start projektu, session, helpery, db())
 * - FULL load: vždy zobrazí výchozí stránku podle přihlášení (nastavení v lib/system.php)
 * - AJAX (partial): vrací jen obsah do <main> podle sekce (bez layoutu)
 * - přepnutí menu režimu: uloží do session přes POST + X-Comeback-Set-Menu
 * - 404: vrátí hlášku a pokusí se zapsat záznam do DB tabulky `chyba`
 * - V12: nepřihlášený uvidí jen hlavičku + modální přihlášení (modaly/modal_login.php)
 * - V15: prvni_login se zobrazuje jako MODÁL (modaly/modal_registrace.php), stejně jako login modal
 * - V16: kontrola spárování mobilu je jen existence aktivního řádku v push_zarizeni; pokud chybí id_user v session, vynutí prvni_login
 * - V17: 2FA (schválení přihlášení) – po zadání hesla čeká na mobil (push_login_2fa); login_ok vzniká až po schválení
 * - V18: 2FA čekací modál: místo URL ukazuje QR kód + text (fallback když notifikace nepřijde)
 * - V19: 2FA čekací modál – CSS přesunuto do style/1/modal_2fa.css (bez inline <style>)
 *
 * Volá / závisí na:
 * - lib/bootstrap.php
 * - lib/nacti_styly.php
 * - includes/hlavicka.php
 * - includes/main.php
 * - includes/paticka.php
 * - includes/dashboard.php
 *
 * Requestuje / čte:
 * - HTTP hlavičky: X-Comeback-Set-Menu, X-Comeback-Partial, X-Comeback-Sekce
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
    if ($m !== 'sidebar') {
        $m = 'dropdown';
    }
    $_SESSION['cb_menu_mode'] = $m;

    http_response_code(204);
    exit;
}

/* =========================
   0b) Nastaveni pobocky do session (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_BRANCH'])
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatny JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idPob = (int)($data['id_pob'] ?? 0);
    if ($idPob <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatna pobocka'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['cb_pobocka_id'] = $idPob;
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   0c) Touch aktivity (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_TOUCH'])
) {
    $nowTs = time();
    if (!isset($_SESSION['cb_session_start_ts']) || (int)$_SESSION['cb_session_start_ts'] <= 0) {
        $_SESSION['cb_session_start_ts'] = $nowTs;
    }
    $_SESSION['cb_last_activity_ts'] = $nowTs;
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

/*
 * Nepřihlášený stav:
 * - login modal
 * - nebo čekání na 2FA po zadání hesla
 */
require_once __DIR__ . '/modaly/modal_overeni.php';

/*
 * Přihlášený bez spárovaného mobilu uvidí modál párování.
 */
require_once __DIR__ . '/lib/kontrola_registrace.php';

$cb_page_exists = $cbPageExists;
$cb_page_file = $file;

require_once __DIR__ . '/includes/main.php';

require_once __DIR__ . '/includes/paticka.php';

?>
</div>

<script src="<?= h(cb_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_url('js/menu_core.js')) ?>"></script>
<script src="<?= h(cb_url('js/menu_ajax.js')) ?>"></script>
<script src="<?= h(cb_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_url('js/filtry_reset.js')) ?>"></script>
<script src="<?= h(cb_url('js/strankovani.js')) ?>"></script>
<script src="<?= h(cb_url('js/casovac_odhlaseni.js')) ?>"></script>

</body>
</html>
<?php
/* index.php * Verze: V22 * Aktualizace: 06.03.2026 */
// Konec souboru
