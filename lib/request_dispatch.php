<?php
// lib/request_dispatch.php * Verze: V2 * Aktualizace: 06.05.2026
declare(strict_types=1);

$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
}

$cbIsCardPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_CARD'])) {
    $cbIsCardPartial = ((string)($_SERVER['HTTP_X_COMEBACK_CARD']) === '1');
}

$cbIsCardMaxPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_CARD_MAX'])) {
    $cbIsCardMaxPartial = ((string)($_SERVER['HTTP_X_COMEBACK_CARD_MAX']) === '1');
}

$cbIsKpiPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_KPI'])) {
    $cbIsKpiPartial = ((string)($_SERVER['HTTP_X_COMEBACK_KPI']) === '1');
}

$cbIsRestiaState = false;
if (isset($_SERVER['HTTP_X_COMEBACK_RESTIA_STATE'])) {
    $cbIsRestiaState = ((string)($_SERVER['HTTP_X_COMEBACK_RESTIA_STATE']) === '1');
}

$cbIsRestiaTrigger = false;
if (isset($_SERVER['HTTP_X_COMEBACK_RESTIA_TRIGGER'])) {
    $cbIsRestiaTrigger = ((string)($_SERVER['HTTP_X_COMEBACK_RESTIA_TRIGGER']) === '1');
}

$cbIsRestiaStop = false;
if (isset($_SERVER['HTTP_X_COMEBACK_RESTIA_STOP'])) {
    $cbIsRestiaStop = ((string)($_SERVER['HTTP_X_COMEBACK_RESTIA_STOP']) === '1');
}

$cbIsUserAkce = false;
if (isset($_SERVER['HTTP_X_COMEBACK_USER_AKCE'])) {
    $cbIsUserAkce = ((string)($_SERVER['HTTP_X_COMEBACK_USER_AKCE']) === '1');
}

$cbIsHelpdesk = false;
if (isset($_SERVER['HTTP_X_COMEBACK_HELPDESK'])) {
    $cbIsHelpdesk = ((string)($_SERVER['HTTP_X_COMEBACK_HELPDESK']) === '1');
}

if ($cbIsHelpdesk) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbHelpdeskAction = trim((string)($_GET['helpdesk_action'] ?? $_POST['helpdesk_action'] ?? ''));
    $cbHelpdeskMap = [
        'vytvorit' => __DIR__ . '/../ajax/helpdesk_vytvorit.php',
        'detail' => __DIR__ . '/../ajax/helpdesk_detail.php',
        'zprava_pridat' => __DIR__ . '/../ajax/helpdesk_zprava_pridat.php',
        'priloha_nahrat' => __DIR__ . '/../ajax/helpdesk_priloha_nahrat.php',
        'sledovat' => __DIR__ . '/../ajax/helpdesk_sledovat.php',
        'stav_zmenit' => __DIR__ . '/../ajax/helpdesk_stav_zmenit.php',
        'notifikace_nacist' => __DIR__ . '/../ajax/helpdesk_notifikace_nacist.php',
        'notifikace_precteno' => __DIR__ . '/../ajax/helpdesk_notifikace_precteno.php',
        'stav_tiketu' => __DIR__ . '/../ajax/helpdesk_stav_tiketu.php',
    ];

    if (!isset($cbHelpdeskMap[$cbHelpdeskAction])) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Neznamy HelpDesk pozadavek'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    define('CB_HELPDESK_DISPATCH_INTERNAL', true);
    require $cbHelpdeskMap[$cbHelpdeskAction];
    exit;
}

if ($cbIsUserAkce && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatny JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $saved = false;
    if (function_exists('cb_user_akce_zapis')) {
        $saved = cb_user_akce_zapis($data);
    }

    echo json_encode([
        'ok' => true,
        'saved' => $saved ? 1 : 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cbIsRestiaStop && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    unset(
        $_SESSION['cb_restia_hist_v4_state'],
        $_SESSION['cb_restia_hist_v4_rows'],
        $_SESSION['cb_restia_hist_v4_msg']
    );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'stopped' => 1], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cbIsRestiaTrigger) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    $db = db();
    $stateSql = "
        SELECT id_akce, id_user, start, konec, zapisy, aktualizace, `ignore`, aktivni
        FROM online_restia
        ORDER BY aktivni DESC, id_akce DESC
        LIMIT 1
    ";
    $readState = static function (mysqli $conn, string $sql): array {
        $res = $conn->query($sql);
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }

        return [
            'active' => ((int)($row['aktivni'] ?? 0) === 1) ? 1 : 0,
            'id_akce' => (int)($row['id_akce'] ?? 0),
            'id_user' => (int)($row['id_user'] ?? 0),
            'start' => trim((string)($row['start'] ?? '')),
            'konec' => trim((string)($row['konec'] ?? '')),
            'zapisy' => (int)($row['zapisy'] ?? 0),
            'aktualizace' => (int)($row['aktualizace'] ?? 0),
            'ignore' => (int)($row['ignore'] ?? 0),
        ];
    };

    $resSet = $db->query('SELECT restia_online FROM set_system WHERE id_set = 1 LIMIT 1');
    $rowSet = ($resSet instanceof mysqli_result) ? $resSet->fetch_assoc() : null;
    if ($resSet instanceof mysqli_result) {
        $resSet->free();
    }

    if ((int)($rowSet['restia_online'] ?? 0) !== 1) {
        echo json_encode([
            'ok' => true,
            'started' => 0,
            'enabled' => 0,
            'active' => 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $forceRestia = ((string)($_SERVER['HTTP_X_COMEBACK_RESTIA_FORCE'] ?? '') === '1');

    require_once __DIR__ . '/restia_online_kontrola.php';
    if (function_exists('cb_restia_online_kontrola')) {
        cb_restia_online_kontrola($forceRestia);
    }

    $stateAfter = $readState($db, $stateSql);
    $started = ((int)($stateAfter['active'] ?? 0) === 1) ? 1 : 0;
    echo json_encode([
        'ok' => true,
        'started' => $started,
        'enabled' => 1,
    ] + $stateAfter, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cbIsRestiaState) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    $db = db();
    $sql = "
        SELECT id_akce, id_user, start, konec, zapisy, aktualizace, `ignore`, aktivni
        FROM online_restia
        ORDER BY aktivni DESC, id_akce DESC
        LIMIT 1
    ";
    $res = $db->query($sql);
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }

    echo json_encode([
        'ok' => true,
        'active' => ((int)($row['aktivni'] ?? 0) === 1) ? 1 : 0,
        'id_akce' => (int)($row['id_akce'] ?? 0),
        'id_user' => (int)($row['id_user'] ?? 0),
        'start' => trim((string)($row['start'] ?? '')),
        'konec' => trim((string)($row['konec'] ?? '')),
        'zapisy' => (int)($row['zapisy'] ?? 0),
        'aktualizace' => (int)($row['aktualizace'] ?? 0),
        'ignore' => (int)($row['ignore'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && isset($_SERVER['HTTP_X_COMEBACK_RESTIA_IMPORT_MAX'])
) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    $cbCardId = (int)($_GET['cb_card_id'] ?? 0);
    $html = '';
    ob_start();
    try {
        require __DIR__ . '/../inicializace/plnime_restia_objednavky.php';
        $html = trim((string)ob_get_clean());
    } catch (Throwable $e) {
        $html = '';
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'err' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($html === '') {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'err' => 'Restia import nevratil obsah.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'cardId' => $cbCardId,
        'cardHtml' => $html,
        'loadMax' => 1,
        'request' => $cbRestiaRawReload ? 'restia_raw_max' : 'restia_import_max',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cbIsCardMaxPartial) {
    $cbCardId = (int)($_GET['cb_card_id'] ?? 0);
    cb_emit_card_max_json_response($cbCardId, 'card_max_partial');
}

if ($cbIsCardPartial) {
    $cbCardId = (int)($_GET['cb_card_id'] ?? 0);
    cb_emit_card_json_response($cbCardId, ((int)($_GET['cb_load_max'] ?? 0) === 1), 'card_partial');
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_MAX_FORM'])
) {
    $cbCardId = (int)($_POST['cb_card_id'] ?? 0);
    $cbRestiaAction = trim((string)($_POST['cb_action'] ?? ''));
    $cbIsRestiaImportFormPost = (
        isset($_POST['run_restia_obj']) && (string)$_POST['run_restia_obj'] === '1'
        && ($cbRestiaAction === 'start' || $cbRestiaAction === 'auto_next')
    );
    if ($cbIsRestiaImportFormPost) {
        header('Content-Type: application/json; charset=utf-8');

        $html = '';
        ob_start();
        try {
            require __DIR__ . '/../inicializace/plnime_restia_objednavky.php';
            $html = trim((string)ob_get_clean());
        } catch (Throwable $e) {
            $html = '';
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'err' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($html === '') {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'err' => 'Restia import nevratil obsah.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'cardId' => $cbCardId,
            'cardHtml' => $html,
            'loadMax' => 1,
            'request' => 'restia_max_form',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    cb_emit_card_json_response($cbCardId, true, 'max_form');
}

if ($cbIsKpiPartial) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');

    if (!isset($cbObdobiOd)) {
        $cbObdobiOd = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
    }
    if (!isset($cbObdobiDo)) {
        $cbObdobiDo = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));
    }

    require __DIR__ . '/../includes/hlavicka/head_kpi.php';
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
