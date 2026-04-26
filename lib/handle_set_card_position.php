<?php
// lib/handle_set_card_position.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

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

        if ($srcCol === $tgtCol && $srcLine === $tgtLine) {
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

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

        $lockedPositions = [];
        $stmtLocked = $conn->prepare('
            SELECT id_karta, col, line
            FROM user_card_set
            WHERE id_user = ?
              AND col IS NOT NULL
              AND line IS NOT NULL
            ORDER BY line ASC, col ASC, id_karta ASC
        ');
        if (!$stmtLocked) {
            throw new RuntimeException('prepare select locked positions failed');
        }
        $stmtLocked->bind_param('i', $idUser);
        $stmtLocked->execute();
        $stmtLocked->bind_result($lockedCardId, $lockedCol, $lockedLine);
        while ($stmtLocked->fetch()) {
            $cardId = (int)$lockedCardId;
            $col = (int)$lockedCol;
            $line = (int)$lockedLine;
            if ($cardId <= 0 || $col <= 0 || $line <= 0) {
                continue;
            }
            $lockedPositions[] = [
                'id_karta' => $cardId,
                'col' => $col,
                'line' => $line,
            ];
        }
        $stmtLocked->close();

        echo json_encode([
            'ok' => true,
            'locked_positions' => $lockedPositions,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni pozice karty selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
