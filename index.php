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
require_once __DIR__ . '/lib/restia_access_exist.php';

/* =========================
   0) Logout (GET)
   ========================= */
if (isset($_GET['action']) && (string)$_GET['action'] === 'logout') {
    $cbUser = $_SESSION['cb_user'] ?? null;
    if (is_array($cbUser) && !empty($cbUser['id_user'])) {
        $idUser = (int)$cbUser['id_user'];
        try {
            $stmt = db()->prepare('INSERT INTO user_login (id_user, akce) VALUES (?,0)');
            if ($stmt) {
                $stmt->bind_param('i', $idUser);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // tichy fail - logout musi pokracovat i kdyz log selze
        }
    }

    $_SESSION = [];
    session_destroy();

    header('Location: ' . cb_url('/'));
    exit;
}

/* =========================
   0a) Registrace zarizeni (JSON)
   ========================= */
if (isset($_GET['action']) && (string)$_GET['action'] === 'registrace_check') {
    header('Content-Type: application/json; charset=utf-8');

    $loginOk = !empty($_SESSION['login_ok']);
    $cbAuthOk = !empty($_SESSION['cb_auth_ok']);
    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

    if ((!$loginOk && !$cbAuthOk) || $idUser <= 0) {
        echo json_encode(['ok' => true, 'paired' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paired = false;
    $stmt = db()->prepare('
        SELECT id
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        LIMIT 1
    ');
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->store_result();
        $paired = ($stmt->num_rows > 0);
        $stmt->close();
    }

    if ($paired && !$loginOk && $cbAuthOk) {
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
    }

    echo json_encode(['ok' => true, 'paired' => $paired], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && (string)$_GET['action'] === 'registrace_abort') {
    header('Content-Type: application/json; charset=utf-8');

    $loginOk = !empty($_SESSION['login_ok']);
    $cbAuthOk = !empty($_SESSION['cb_auth_ok']);
    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

    if (($loginOk || $cbAuthOk) && $idUser > 0) {
        $stmt = db()->prepare('
            UPDATE push_parovani
            SET aktivni=0
            WHERE id_user=? AND aktivni=1 AND pouzito_kdy IS NULL
        ');
        if ($stmt) {
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $stmt->close();
        }
    }

    $_SESSION = [];
    session_destroy();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   0) Nastaveni pobocky do session (POST)
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

</body>
</html>
<?php
/* index.php * Verze: V22 * Aktualizace: 06.03.2026 */
// Konec souboru

