<?php
// ajax/helpdesk_stav_tiketu.php * Verze: V1 * Aktualizace: 24.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../../www/lib/session_boot.php';
    require_once __DIR__ . '/../../www/lib/app.php';
}
require_once __DIR__ . '/../lib/helpdesk_prava.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idUser = cb_helpdesk_current_user_id();
    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }

    $conn = db();
    $scope = cb_helpdesk_visible_scope($idUser);
    $counts = [
        'all' => 0,
        'new' => 0,
        'active' => 0,
        'resolved' => 0,
    ];

    $sqlCounts = '
        SELECT
            SUM(CASE
                WHEN (
                    hr.id_helpdesk_read IS NULL
                    OR (h.posledni_zprava IS NOT NULL AND h.posledni_zprava > hr.precteno)
                ) AND h.stav = \'nový\' THEN 1
                ELSE 0
            END) AS new_cnt,
            SUM(CASE
                WHEN (
                    hr.id_helpdesk_read IS NULL
                    OR (h.posledni_zprava IS NOT NULL AND h.posledni_zprava > hr.precteno)
                ) AND h.stav = \'řeší se\' THEN 1
                ELSE 0
            END) AS active_cnt,
            SUM(CASE
                WHEN (
                    hr.id_helpdesk_read IS NULL
                    OR (h.posledni_zprava IS NOT NULL AND h.posledni_zprava > hr.precteno)
                ) AND h.stav = \'vyřešeno\' THEN 1
                ELSE 0
            END) AS resolved_cnt
        FROM helpdesk h
        LEFT JOIN helpdesk_read hr
               ON hr.id_helpdesk = h.id_helpdesk
              AND hr.id_user = ?
        WHERE ' . $scope['sql'] . '
    ';

    $stmtCounts = $conn->prepare($sqlCounts);
    if (!($stmtCounts instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se načíst počty tiketů.');
    }

    if (($scope['types'] ?? '') === '') {
        $stmtCounts->bind_param('i', $idUser);
    } else {
        $types = 'i' . (string)$scope['types'];
        $params = [$idUser];
        foreach ((array)($scope['params'] ?? []) as $value) {
            $params[] = $value;
        }

        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmtCounts, 'bind_param'], $bind);
    }

    $stmtCounts->execute();
    $resCounts = $stmtCounts->get_result();
    if ($resCounts instanceof mysqli_result) {
        $rowCounts = $resCounts->fetch_assoc() ?: [];
        $counts['new'] = (int)($rowCounts['new_cnt'] ?? 0);
        $counts['active'] = (int)($rowCounts['active_cnt'] ?? 0);
        $counts['resolved'] = (int)($rowCounts['resolved_cnt'] ?? 0);
        $counts['all'] = $counts['new'] + $counts['active'] + $counts['resolved'];
        $resCounts->free();
    }
    $stmtCounts->close();

    $sql = '
        SELECT
            h.id_helpdesk,
            CASE
                WHEN hr.id_helpdesk_read IS NULL THEN 1
                WHEN h.posledni_zprava IS NOT NULL AND h.posledni_zprava > hr.precteno THEN 1
                ELSE 0
            END AS has_new_reply
        FROM helpdesk h
        LEFT JOIN helpdesk_read hr
               ON hr.id_helpdesk = h.id_helpdesk
              AND hr.id_user = ?
        WHERE ' . $scope['sql'] . '
        ORDER BY FIELD(h.stav, \'nový\', \'řeší se\', \'vyřešeno\'), h.upraveno DESC, h.vytvoreno DESC
        LIMIT 120
    ';

    $stmt = $conn->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se načíst stav tiketů.');
    }

    if (($scope['types'] ?? '') === '') {
        $stmt->bind_param('i', $idUser);
    } else {
        $types = 'i' . (string)$scope['types'];
        $params = [$idUser];
        foreach ((array)($scope['params'] ?? []) as $value) {
            $params[] = $value;
        }

        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $tickets = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $tickets[] = [
                'id_helpdesk' => (int)($row['id_helpdesk'] ?? 0),
                'has_new_reply' => (int)($row['has_new_reply'] ?? 0),
            ];
        }
        $res->free();
    }
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'counts' => $counts,
        'tickets' => $tickets,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_stav_tiketu.php * Verze: V1 * Aktualizace: 24.06.2026
// Konec souboru
