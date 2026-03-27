<?php
if (!function_exists('cb_pobocky_save_selection_to_db')) {
    /**
     * @param int[] $ids
     */
    function cb_pobocky_save_selection_to_db(int $idUser, array $ids): void
    {
        $clean = cb_pobocky_sanitize_ids($ids);
        if ($idUser <= 0 || !$clean) {
            throw new RuntimeException('Neplatny vyber pobocky pro DB ulozeni.');
        }

        $conn = db();

        $conn->begin_transaction();
        try {
            $stmtDel = $conn->prepare('DELETE FROM user_pobocka_set WHERE id_user = ?');
            if ($stmtDel === false) {
                throw new RuntimeException('Nepodarilo se pripravit mazani user_pobocka_set.');
            }
            $stmtDel->bind_param('i', $idUser);
            $stmtDel->execute();
            $stmtDel->close();

            $stmtIns = $conn->prepare('INSERT INTO user_pobocka_set (id_user, id_pob) VALUES (?, ?)');
            if ($stmtIns === false) {
                throw new RuntimeException('Nepodarilo se pripravit vlozeni user_pobocka_set.');
            }

            foreach ($clean as $idPob) {
                $idPob = (int)$idPob;
                $stmtIns->bind_param('ii', $idUser, $idPob);
                $stmtIns->execute();
            }
            $stmtIns->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
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

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($idUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $allowed = cb_pobocky_get_allowed_for_user($idUser);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Nelze nacist povolene pobocky'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $allowedIds = $allowed['ids'];
    if (!in_array($idPob, $allowedIds, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Pobocka neni uzivateli povolena'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        cb_pobocky_save_selection_to_db($idUser, [$idPob]);
        cb_pobocky_set_selected([$idPob]);
        cb_pobocky_set_mode('single', null);
        $_SESSION['cb_pobocka_id'] = $idPob;
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni vyberu pobocky selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =========================
   0b) Nastaveni multi-vyberu pobocek (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_BRANCHES'])
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
        echo json_encode(['ok' => false, 'err' => 'Neplatný JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mode = trim((string)($data['mode'] ?? ''));
    if (!in_array($mode, ['area', 'custom'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatný režim výběru'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $allowed = cb_pobocky_get_allowed_for_user($idUser);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Nelze načíst povolené pobočky'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowedIds = $allowed['ids'];
    $allowedIdSet = array_fill_keys($allowedIds, true);
    if (!$allowedIds) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Uživatel nemá povolené pobočky'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($mode === 'area') {
        $rawOblasti = $data['selected_oblasti'] ?? [];
        if (!is_array($rawOblasti)) {
            $rawOblasti = [];
        }
        $singleOblast = trim((string)($data['selected_oblast'] ?? ''));
        if ($singleOblast !== '') {
            $rawOblasti[] = $singleOblast;
        }

        $selectedOblastiMap = [];
        foreach ($rawOblasti as $o) {
            $o = trim((string)$o);
            if ($o !== '') {
                $selectedOblastiMap[$o] = true;
            }
        }
        $selectedOblasti = array_keys($selectedOblastiMap);
        sort($selectedOblasti);

        if (!$selectedOblasti) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Oblast není vybrána'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $areaMap = $allowed['oblasti'];
        $idsMap = [];
        foreach ($selectedOblasti as $oblast) {
            $areaIds = $areaMap[$oblast] ?? [];
            foreach ($areaIds as $idPob) {
                $idsMap[(int)$idPob] = true;
            }
        }
        $ids = array_keys($idsMap);
        sort($ids);
        if (!$ids) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Oblast nemá povolené pobočky'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            cb_pobocky_save_selection_to_db($idUser, $ids);
            cb_pobocky_set_selected($ids);
            cb_pobocky_set_mode('area', $selectedOblasti[0]);
            $_SESSION['selected_oblasti'] = $selectedOblasti;
            echo json_encode(['ok' => true, 'count' => count($ids), 'oblasti' => $selectedOblasti], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'err' => 'Ulozeni vyberu pobocek selhalo'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $rawIds = $data['selected_pobocky'] ?? [];
    $ids = cb_pobocky_sanitize_ids($rawIds);
    if (!$ids) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Není vybrána žádná pobočka'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $valid = [];
    foreach ($ids as $id) {
        if (isset($allowedIdSet[$id])) {
            $valid[] = $id;
        }
    }
    if (!$valid) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Vybrané pobočky nejsou povolené'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        cb_pobocky_save_selection_to_db($idUser, $valid);
        cb_pobocky_set_selected($valid);
        cb_pobocky_set_mode('custom', null);
        $_SESSION['selected_oblasti'] = [];
        echo json_encode(['ok' => true, 'count' => count($valid)], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni vyberu pobocek selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
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
