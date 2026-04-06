<?php
// index.php * Verze: V22 * Aktualizace: 06.03.2026

/*
 * FRONT CONTROLLER (centralni vstup aplikace)
 *
 * Co dela:
 * - startuje session + nacita app/system/secrets
 * - FULL load: zobrazi vychozi stranku podle prihlaseni
 * - AJAX (partial): vraci jen obsah do <main>
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
require_once __DIR__ . '/lib/pobocky_vyber.php';

cb_pobocky_bootstrap_session();

require_once __DIR__ . '/lib/detektuj_neplatnou_url.php';

require_once __DIR__ . '/lib/logout_handler.php';

require_once __DIR__ . '/lib/json_registrace.php';

require_once __DIR__ . '/lib/post_akce.php';

/* =========================
   0d) Nastaveni obdobi (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_PERIOD'])
) {
    header('Content-Type: application/json; charset=utf-8');

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($idUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatny JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hasOd = array_key_exists('od', $data);
    $hasDo = array_key_exists('do', $data);
    if (!$hasOd && !$hasDo) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Chybi od/do'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isDate = static function (string $v): bool {
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $v)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        return checkdate($m, $d, $y);
    };

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    try {
        $conn = db();
        $stmtCur = $conn->prepare('SELECT obdobi_od, obdobi_do FROM user_set WHERE id_user = ? LIMIT 1');
        if (!$stmtCur) {
            throw new RuntimeException('prepare select failed');
        }
        $stmtCur->bind_param('i', $idUser);
        $stmtCur->execute();
        $stmtCur->bind_result($dbOd, $dbDo);
        $hasRow = $stmtCur->fetch();
        $stmtCur->close();

        if (!$hasRow) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Nenalezen user_set'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentOd = trim((string)($dbOd ?? ''));
        $currentDo = trim((string)($dbDo ?? ''));

        if (!$isDate($currentOd)) {
            $currentOd = (string)($_SESSION['cb_obdobi_od'] ?? '');
        }
        if (!$isDate($currentDo)) {
            $currentDo = (string)($_SESSION['cb_obdobi_do'] ?? '');
        }
        if (!$isDate($currentOd)) {
            $currentOd = $today;
        }
        if (!$isDate($currentDo)) {
            $currentDo = $today;
        }

        $newOd = $currentOd;
        $newDo = $currentDo;

        if ($hasOd) {
            $newOd = trim((string)($data['od'] ?? ''));
            if (!$isDate($newOd)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'err' => 'Neplatne od'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($newOd > $today) {
                $newOd = $today;
            }
        }

        if ($hasDo) {
            $newDo = trim((string)($data['do'] ?? ''));
            if (!$isDate($newDo)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'err' => 'Neplatne do'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($newDo > $today) {
                $newDo = $today;
            }
        }

        if ($newOd > $newDo) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Neplatne poradi dat'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($hasOd && $hasDo) {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_od = ?, obdobi_do = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update failed');
            }
            $stmtUpd->bind_param('ssi', $newOd, $newDo, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        } elseif ($hasOd) {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_od = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update od failed');
            }
            $stmtUpd->bind_param('si', $newOd, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_do = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update do failed');
            }
            $stmtUpd->bind_param('si', $newDo, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        $_SESSION['cb_obdobi_od'] = $newOd;
        $_SESSION['cb_obdobi_do'] = $newDo;

        echo json_encode([
            'ok' => true,
            'od' => $newOd,
            'do' => $newDo,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni obdobi selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =========================
   0e) Nastaveni rezimu karty (nano/mini/maxi)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_CARD_MODE'])
) {
    header('Content-Type: application/json; charset=utf-8');

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($idUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatny JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idKarta = (int)($data['id_karta'] ?? 0);
    $mode = trim((string)($data['mode'] ?? ''));
    if ($idKarta <= 0 || !in_array($mode, ['mini', 'maxi', 'nano'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatny vstup'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = db();
        if ($mode === 'nano') {
            $stmt = $conn->prepare('INSERT IGNORE INTO user_nano (id_user, id_nano) VALUES (?, ?)');
            if (!$stmt) {
                throw new RuntimeException('prepare insert user_nano failed');
            }
            $stmt->bind_param('ii', $idUser, $idKarta);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare('DELETE FROM user_nano WHERE id_user = ? AND id_nano = ?');
            if (!$stmt) {
                throw new RuntimeException('prepare delete user_nano failed');
            }
            $stmt->bind_param('ii', $idUser, $idKarta);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni rezimu karty selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =========================
   0f) Globalni PRG (POST -> Redirect -> GET)
   ========================= */
$cbPrgKey = 'cb_prg_post_payload_v1';
$cbRequestUri = (string)($_SERVER['REQUEST_URI'] ?? '/index.php');
$cbRequestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($cbRequestMethod === 'GET' && isset($_SESSION[$cbPrgKey]) && is_array($_SESSION[$cbPrgKey])) {
    $cbPrgPayload = $_SESSION[$cbPrgKey];
    unset($_SESSION[$cbPrgKey]);

    $cbPrgTs = (int)($cbPrgPayload['ts'] ?? 0);
    $cbPrgUri = (string)($cbPrgPayload['uri'] ?? '');
    $cbPrgPost = $cbPrgPayload['post'] ?? [];

    $cbAgeOk = ($cbPrgTs > 0 && (time() - $cbPrgTs) <= 300);
    $cbUriOk = ($cbPrgUri !== '' && $cbPrgUri === $cbRequestUri);

    if ($cbAgeOk && $cbUriOk && is_array($cbPrgPost)) {
        $_POST = $cbPrgPost;
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
    }
}

if ($cbRequestMethod === 'POST') {
    $cbIsAjaxPost = false;
    foreach (array_keys($_SERVER) as $cbHeaderName) {
        if (strpos((string)$cbHeaderName, 'HTTP_X_COMEBACK_') === 0) {
            $cbIsAjaxPost = true;
            break;
        }
    }

    if (!$cbIsAjaxPost) {
        $_SESSION[$cbPrgKey] = [
            'ts' => time(),
            'uri' => $cbRequestUri,
            'post' => $_POST,
        ];
        header('Location: ' . $cbRequestUri, true, 303);
        exit;
    }
}

/* =========================
   1) AJAX (partial) rezim
   ========================= */
$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
}

/* =========================
   2) Dashboard (bez sekci)
   ========================= */
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
        echo '<section class="card odstup_vnitrni_14"><p>Nutne prihlaseni.</p></section>';
        exit;
    }

    if ($cbPageExists) {
        require $file;
    } else {
        echo '<div class="page-head"><h2>Stranka nenalezena</h2></div>';
        echo '<section class="card odstup_vnitrni_14"><p>Pozadovana stranka neexistuje.</p></section>';
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

  <div class="container bg_modra displ_flex sirka100">
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
<script src="<?= h(cb_asset_url('js/karty_min_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_hlavicka.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_restia.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_form.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_person.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/select_pobocky.js')) ?>"></script>
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
