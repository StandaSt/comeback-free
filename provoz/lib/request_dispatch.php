<?php
// lib/request_dispatch.php * Verze: V2 * Aktualizace: 06.05.2026
declare(strict_types=1);

if (isset($_SERVER['HTTP_X_COMEBACK_AI_ANALYTIK'])
    && (string)$_SERVER['HTTP_X_COMEBACK_AI_ANALYTIK'] === '1') {
    require __DIR__ . '/ai_analytik_gateway.php';
    exit;
}

$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
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

if (isset($_SERVER['HTTP_X_COMEBACK_SMENY_PLAN_STATE']) && (string)($_SERVER['HTTP_X_COMEBACK_SMENY_PLAN_STATE']) === '1') {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lastSync = '';
    $shouldRun = false;
    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') {
        $db = db();
        $res = $db->query('SELECT MAX(finished_at) AS last_sync FROM smeny_aktualizace');
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $lastSync = trim((string)($row['last_sync'] ?? ''));
        $lastSyncTs = ($lastSync !== '') ? strtotime($lastSync) : false;
        $shouldRun = ($lastSyncTs === false || $lastSyncTs < (time() - 3600));
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'last_sync' => $lastSync,
        'should_run' => $shouldRun ? 1 : 0,
    ], JSON_UNESCAPED_UNICODE);
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

    $cbRestiaModule = strtolower(trim((string)($_SERVER['HTTP_X_COMEBACK_MODULE'] ?? '')));
    if ($cbRestiaModule !== 'provoz') {
        echo json_encode([
            'ok' => true,
            'started' => 0,
            'enabled' => 1,
            'active' => 0,
            'skipped_module' => 1,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

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
