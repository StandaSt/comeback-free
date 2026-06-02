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

    if ($srcId <= 0 || $tgtId <= 0 || $srcId === $tgtId) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatny vstup'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = db();

        $conn->begin_transaction();
        try {
            $orders = [];
            $stmtOrders = $conn->prepare('
                SELECT k.id_karta, COALESCE(ucs.poradi, k.poradi) AS poradi
                FROM karty k
                LEFT JOIN user_card_set ucs
                  ON ucs.id_user = ?
                 AND ucs.id_karta = k.id_karta
                WHERE k.id_karta IN (?, ?)
                LIMIT 2
            ');
            if (!$stmtOrders) {
                throw new RuntimeException('prepare select card order failed');
            }
            $stmtOrders->bind_param('iii', $idUser, $srcId, $tgtId);
            $stmtOrders->execute();
            $stmtOrders->bind_result($orderCardId, $orderValue);
            while ($stmtOrders->fetch()) {
                $cid = (int)$orderCardId;
                $order = (int)$orderValue;
                if ($cid > 0 && $order > 0) {
                    $orders[$cid] = $order;
                }
            }
            $stmtOrders->close();

            if (!isset($orders[$srcId], $orders[$tgtId])) {
                throw new RuntimeException('card order not found');
            }

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

            $srcOrder = (int)$orders[$srcId];
            $tgtOrder = (int)$orders[$tgtId];

            $stmtSrc = $conn->prepare('UPDATE user_card_set SET poradi = ? WHERE id_user = ? AND id_karta = ?');
            if (!$stmtSrc) {
                throw new RuntimeException('prepare set source order failed');
            }
            $stmtSrc->bind_param('iii', $tgtOrder, $idUser, $srcId);
            $stmtSrc->execute();
            $stmtSrc->close();

            $stmtTgt = $conn->prepare('UPDATE user_card_set SET poradi = ? WHERE id_user = ? AND id_karta = ?');
            if (!$stmtTgt) {
                throw new RuntimeException('prepare set target order failed');
            }
            $stmtTgt->bind_param('iii', $srcOrder, $idUser, $tgtId);
            $stmtTgt->execute();
            $stmtTgt->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        $cardOrders = [];
        $stmtOrderList = $conn->prepare('
            SELECT id_karta, poradi
            FROM user_card_set
            WHERE id_user = ?
              AND poradi IS NOT NULL
            ORDER BY poradi ASC, id_karta ASC
        ');
        if (!$stmtOrderList) {
            throw new RuntimeException('prepare select card order list failed');
        }
        $stmtOrderList->bind_param('i', $idUser);
        $stmtOrderList->execute();
        $stmtOrderList->bind_result($orderedCardId, $orderedValue);
        while ($stmtOrderList->fetch()) {
            $cardId = (int)$orderedCardId;
            $order = (int)$orderedValue;
            if ($cardId <= 0 || $order <= 0) {
                continue;
            }
            $cardOrders[] = [
                'id_karta' => $cardId,
                'poradi' => $order,
            ];
        }
        $stmtOrderList->close();

        echo json_encode([
            'ok' => true,
            'card_orders' => $cardOrders,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni poradi karty selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
