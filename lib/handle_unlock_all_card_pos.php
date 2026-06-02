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

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE user_card_set SET poradi = NULL WHERE id_user = ?');
            if (!$stmt) {
                throw new RuntimeException('prepare reset all card order failed');
            }
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $stmt->close();

            $stmtClean = $conn->prepare('
                DELETE FROM user_card_set
                WHERE id_user = ?
                  AND color IS NULL
                  AND ikon IS NULL
                  AND col IS NULL
                  AND line IS NULL
                  AND poradi IS NULL
            ');
            if (!$stmtClean) {
                throw new RuntimeException('prepare clean empty user card rows failed');
            }
            $stmtClean->bind_param('i', $idUser);
            $stmtClean->execute();
            $stmtClean->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        $cardOrders = [];
        $stmtOrder = $conn->prepare('
            SELECT id_karta, poradi
            FROM user_card_set
            WHERE id_user = ?
              AND poradi IS NOT NULL
            ORDER BY poradi ASC, id_karta ASC
        ');
        if (!$stmtOrder) {
            throw new RuntimeException('prepare select card order failed');
        }
        $stmtOrder->bind_param('i', $idUser);
        $stmtOrder->execute();
        $stmtOrder->bind_result($orderCardId, $orderValue);
        while ($stmtOrder->fetch()) {
            $cardId = (int)$orderCardId;
            $order = (int)$orderValue;
            if ($cardId <= 0 || $order <= 0) {
                continue;
            }
            $cardOrders[] = [
                'id_karta' => $cardId,
                'poradi' => $order,
            ];
        }
        $stmtOrder->close();

        echo json_encode([
            'ok' => true,
            'card_orders' => $cardOrders,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Obnoveni vychoziho poradi karet selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
