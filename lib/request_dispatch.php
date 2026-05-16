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

    $stateBefore = $readState($db, $stateSql);
    if ((int)($stateBefore['active'] ?? 0) === 1) {
        echo json_encode([
            'ok' => true,
            'started' => 0,
            'enabled' => 1,
        ] + $stateBefore, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!defined('CB_RESTIA_ONLINE_KONTROLA_AUTO_RUN')) {
        define('CB_RESTIA_ONLINE_KONTROLA_AUTO_RUN', false);
    }
    require_once __DIR__ . '/restia_online_kontrola.php';
    if (function_exists('cb_restia_online_kontrola')) {
        cb_restia_online_kontrola();
    }

    $stateAfter = $readState($db, $stateSql);
    echo json_encode([
        'ok' => true,
        'started' => 1,
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
