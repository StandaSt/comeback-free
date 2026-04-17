<?php
// index.php * Verze: V24 * Aktualizace: 15.04.2026

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/system.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/pobocky_vyber.php';


$cbTitle = 'Comeback - IS';
$cbFavicon = cb_url('img/favicon_comeback.png');

cb_pobocky_bootstrap_session();

require_once __DIR__ . '/lib/detektuj_neplatnou_url.php';
require_once __DIR__ . '/lib/logout_handler.php';
require_once __DIR__ . '/lib/json_registrace.php';
require_once __DIR__ . '/lib/post_akce.php';

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
            $maxNano = 9;
            $stmtCnt = $conn->prepare('SELECT COUNT(*) FROM user_nano WHERE id_user = ? AND id_nano <> ?');
            if (!$stmtCnt) {
                throw new RuntimeException('prepare count user_nano failed');
            }
            $stmtCnt->bind_param('ii', $idUser, $idKarta);
            $stmtCnt->execute();
            $stmtCnt->bind_result($nanoCount);
            $stmtCnt->fetch();
            $stmtCnt->close();

            if ((int)$nanoCount >= $maxNano) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'err' => 'Nano režim je omezen na 9 karet. Desátou kartu nelze přidat.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $conn->prepare('INSERT IGNORE INTO user_nano (id_user, id_nano) VALUES (?, ?)');
            if (!$stmt) {
                throw new RuntimeException('prepare insert user_nano failed');
            }
            $stmt->bind_param('ii', $idUser, $idKarta);
            $stmt->execute();
            $stmt->close();

            $stmtUnlock = $conn->prepare('UPDATE user_card_set SET col = NULL, line = NULL WHERE id_user = ? AND id_karta = ?');
            if (!$stmtUnlock) {
                throw new RuntimeException('prepare unlock card position failed');
            }
            $stmtUnlock->bind_param('ii', $idUser, $idKarta);
            $stmtUnlock->execute();
            $stmtUnlock->close();
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

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_UNLOCK_ALL_CARD_POS'])
) {
    header('Content-Type: application/json; charset=utf-8');

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($idUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = db();
        $stmt = $conn->prepare('UPDATE user_card_set SET col = NULL, line = NULL WHERE id_user = ?');
        if (!$stmt) {
            throw new RuntimeException('prepare unlock all card positions failed');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Odemknuti pozic karet selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_CARD_POSITION'])
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

    $srcId = (int)($data['src_id'] ?? 0);
    $tgtId = (int)($data['tgt_id'] ?? 0);
    $srcCol = (int)($data['src_col'] ?? 0);
    $srcLine = (int)($data['src_line'] ?? 0);
    $tgtCol = (int)($data['tgt_col'] ?? 0);
    $tgtLine = (int)($data['tgt_line'] ?? 0);
    $targetLocked = ((int)($data['target_locked'] ?? 0) === 1);
    $forceUnlock = ((int)($data['force_unlock'] ?? 0) === 1);

    if ($srcId <= 0 || $tgtId <= 0 || $srcId === $tgtId) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatny vstup'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($srcCol <= 0 || $srcLine <= 0 || $tgtCol <= 0 || $tgtLine <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatna pozice'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($tgtCol === 1 && $tgtLine === 1) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Pozice 1-1 je určena pro nano karty.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = db();

        $stmtTitle = $conn->prepare('SELECT nazev FROM karty WHERE id_karta = ? LIMIT 1');
        $targetTitle = '';
        if ($stmtTitle) {
            $stmtTitle->bind_param('i', $tgtId);
            $stmtTitle->execute();
            $stmtTitle->bind_result($tt);
            if ($stmtTitle->fetch()) {
                $targetTitle = trim((string)$tt);
            }
            $stmtTitle->close();
        }

        if ($targetLocked && !$forceUnlock) {
            echo json_encode([
                'ok' => false,
                'needs_confirm' => true,
                'target_title' => $targetTitle,
                'err' => 'Cilova karta ma uzamcenou pozici'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmtUpsert = $conn->prepare('
                INSERT INTO user_card_set (id_user, id_karta)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE id_karta = VALUES(id_karta)
            ');
            if (!$stmtUpsert) {
                throw new RuntimeException('prepare upsert user_card_set failed');
            }
            $stmtUpsert->bind_param('ii', $idUser, $srcId);
            $stmtUpsert->execute();
            $stmtUpsert->bind_param('ii', $idUser, $tgtId);
            $stmtUpsert->execute();
            $stmtUpsert->close();

            $stmtSrc = $conn->prepare('UPDATE user_card_set SET col = ?, line = ? WHERE id_user = ? AND id_karta = ?');
            if (!$stmtSrc) {
                throw new RuntimeException('prepare set source position failed');
            }
            $stmtSrc->bind_param('iiii', $tgtCol, $tgtLine, $idUser, $srcId);
            $stmtSrc->execute();
            $stmtSrc->close();

            $stmtTgt = $conn->prepare('UPDATE user_card_set SET col = NULL, line = NULL WHERE id_user = ? AND id_karta = ?');
            if (!$stmtTgt) {
                throw new RuntimeException('prepare clear target position failed');
            }
            $stmtTgt->bind_param('ii', $idUser, $tgtId);
            $stmtTgt->execute();
            $stmtTgt->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni pozice karty selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

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

$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
}

$cbIsCardPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_CARD'])) {
    $cbIsCardPartial = ((string)($_SERVER['HTTP_X_COMEBACK_CARD']) === '1');
}

$pageKey = 'dashboard';
$file = __DIR__ . '/includes/dashboard.php';
$cbStartupLoaderText = trim((string)($_SESSION['cb_initial_loader_text'] ?? ''));
if ($cbStartupLoaderText !== '') {
    unset($_SESSION['cb_initial_loader_text']);
}

$cbStartupLoaderHtml = '';
$cbShowStartupLoader = !empty($_SESSION['login_ok']);
if ($cbShowStartupLoader) {
    ob_start();
    require __DIR__ . '/includes/loaders/dashboard.php';
    $cbStartupLoaderHtml = trim((string)ob_get_clean());
    if ($cbStartupLoaderHtml !== '') {
        $cbStartupLoaderHtml = preg_replace(
            '~<div class="dash_loader is-hidden" data-cb-loader-mode="dashboard" aria-hidden="true">~',
            '<div class="dash_loader" data-cb-loader-mode="dashboard" aria-hidden="false" data-cb-loader-visible="1">',
            $cbStartupLoaderHtml,
            1
        ) ?? $cbStartupLoaderHtml;
        if ($cbStartupLoaderText !== '') {
            $cbStartupLoaderHtml = preg_replace(
                '~<p class="dash_loader_text">.*?</p>~s',
                '<p class="dash_loader_text">' . h($cbStartupLoaderText) . '</p>',
                $cbStartupLoaderHtml,
                1
            ) ?? $cbStartupLoaderHtml;
        }
    }
}

require_once __DIR__ . '/includes/log_a_404.php';

if ($cbIsCardPartial) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo '<section class="card odstup_vnitrni_14"><p>Nutne prihlaseni.</p></section>';
        exit;
    }

    $cbCardId = (int)($_GET['cb_card_id'] ?? 0);
    if ($cbCardId <= 0) {
        http_response_code(422);
        echo '<section class="card odstup_vnitrni_14"><p>Neplatna karta.</p></section>';
        exit;
    }

    $GLOBALS['cb_dashboard_single_card_id'] = $cbCardId;

    if ($cbPageExists) {
        require $file;
    } else {
        echo '<section class="card odstup_vnitrni_14"><p>Pozadovana karta neexistuje.</p></section>';
    }
    exit;
}

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
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($cbTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= h($cbFavicon) ?>">

    <?php require_once __DIR__ . '/lib/nacti_styly.php'; ?>
</head>
<body>

<?php if ($cbShowStartupLoader && $cbStartupLoaderHtml !== ''): ?>
<div id="cb-startup-loader" class="dash_box bg_modra sirka100 is-dashboard-loading" data-cb-startup-text="<?= h($cbStartupLoaderText) ?>" style="position:fixed;left:0;top:0;right:0;bottom:0;z-index:12000;padding:0 12px;background-clip:content-box;overflow:hidden;">
  <?= $cbStartupLoaderHtml ?>
</div>
<script src="<?= h(cb_url('js/loader_show.js')) ?>"></script>
<script src="<?= h(cb_url('js/loader_timer.js')) ?>"></script>
<?php endif; ?>
<div class="container bg_modra displ_flex sirka100">
<?php

require_once __DIR__ . '/includes/hlavicka.php';
require_once __DIR__ . '/modaly/modal_overeni.php';
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


<script src="<?= h(cb_asset_url('js/echarts.min.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/chart.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_karta_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_grafy.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_nano.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_hlavicka.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_restia.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_form.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_person.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/select_pobocky.js')) ?>"></script>
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
/* index.php * Verze: V24 * Aktualizace: 15.04.2026 */
// Konec souboru
