<?php
// lib/handle_unlock_all_card_pos.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

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
        echo json_encode(['ok' => false, 'err' => 'Odemknuti pozic karet selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
