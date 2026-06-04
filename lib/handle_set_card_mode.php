<?php
// lib/handle_set_card_mode.php * Verze: V2 * Aktualizace: 04.06.2026
declare(strict_types=1);

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
        $idRole = (is_array($cbUser) && isset($cbUser['id_role'])) ? (int)$cbUser['id_role'] : 9;
        if ($idRole <= 0) {
            $idRole = 9;
        }

        $parseCardOrderIds = static function (?string $value): array {
            $raw = trim((string)$value);
            if ($raw === '') {
                return [];
            }

            $ids = [];
            foreach (explode(',', $raw) as $part) {
                $id = (int)trim($part);
                if ($id > 0 && !isset($ids[$id])) {
                    $ids[$id] = $id;
                }
            }

            return array_values($ids);
        };

        $orderBySavedIds = static function (array $fallbackIds, array $savedIds): array {
            if (empty($savedIds)) {
                return $fallbackIds;
            }

            $available = array_fill_keys($fallbackIds, true);
            $used = [];
            $result = [];

            foreach ($savedIds as $idKarta) {
                $idKarta = (int)$idKarta;
                if ($idKarta > 0 && isset($available[$idKarta]) && !isset($used[$idKarta])) {
                    $result[] = $idKarta;
                    $used[$idKarta] = true;
                }
            }

            foreach ($fallbackIds as $idKarta) {
                if (!isset($used[$idKarta])) {
                    $result[] = $idKarta;
                }
            }

            return $result;
        };

        $removeCardId = static function (array $ids, int $idKarta): array {
            $result = [];
            foreach ($ids as $id) {
                $id = (int)$id;
                if ($id > 0 && $id !== $idKarta) {
                    $result[] = $id;
                }
            }
            return $result;
        };

        $conn->begin_transaction();
        try {
            $savedMiniRaw = null;
            $savedNanoRaw = null;
            $stmtSaved = $conn->prepare('SELECT poradi_mini, poradi_nano FROM user_set WHERE id_user = ? LIMIT 1');
            if (!$stmtSaved) {
                throw new RuntimeException('prepare select user orders failed');
            }
            $stmtSaved->bind_param('i', $idUser);
            $stmtSaved->execute();
            $stmtSaved->bind_result($savedMiniRaw, $savedNanoRaw);
            $stmtSaved->fetch();
            $stmtSaved->close();

            $savedMiniIds = $parseCardOrderIds($savedMiniRaw === null ? null : (string)$savedMiniRaw);
            $savedNanoIds = $parseCardOrderIds($savedNanoRaw === null ? null : (string)$savedNanoRaw);
            $savedNanoSet = empty($savedNanoIds) ? [] : array_fill_keys($savedNanoIds, true);

            $fallbackMiniIds = [];
            $fallbackNanoIds = [];
            $stmtCards = $conn->prepare('
                SELECT id_karta
                FROM karty
                WHERE aktivni = 1
                  AND min_role >= ?
                ORDER BY poradi ASC, id_karta ASC
            ');
            if (!$stmtCards) {
                throw new RuntimeException('prepare select cards failed');
            }
            $stmtCards->bind_param('i', $idRole);
            $stmtCards->execute();
            $stmtCards->bind_result($cardIdDb);
            while ($stmtCards->fetch()) {
                $cardId = (int)$cardIdDb;
                if ($cardId <= 0) {
                    continue;
                }
                if (isset($savedNanoSet[$cardId])) {
                    $fallbackNanoIds[] = $cardId;
                } else {
                    $fallbackMiniIds[] = $cardId;
                }
            }
            $stmtCards->close();

            $miniIds = $orderBySavedIds($fallbackMiniIds, $savedMiniIds);
            $nanoIds = $orderBySavedIds($fallbackNanoIds, $savedNanoIds);

            if ($mode === 'nano') {
                $isAlreadyNano = in_array($idKarta, $nanoIds, true);
                if (!$isAlreadyNano && count($nanoIds) >= 9) {
                    $conn->rollback();
                    http_response_code(409);
                    echo json_encode(['ok' => false, 'err' => 'Nano reĹľim je omezen na 9 karet. DesĂˇtou kartu nelze pĹ™idat.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $miniIds = $removeCardId($miniIds, $idKarta);
                $nanoIds = $removeCardId($nanoIds, $idKarta);
                $nanoIds[] = $idKarta;
            } else {
                $nanoIds = $removeCardId($nanoIds, $idKarta);
                $miniIds = $removeCardId($miniIds, $idKarta);
                $miniIds[] = $idKarta;
            }

            $miniOrder = empty($miniIds) ? null : implode(',', array_map('strval', $miniIds));
            $nanoOrder = empty($nanoIds) ? null : implode(',', array_map('strval', $nanoIds));

            $stmtSave = $conn->prepare('
                INSERT INTO user_set (id_user, poradi_mini, poradi_nano)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    poradi_mini = VALUES(poradi_mini),
                    poradi_nano = VALUES(poradi_nano)
            ');
            if (!$stmtSave) {
                throw new RuntimeException('prepare save card orders failed');
            }
            $stmtSave->bind_param('iss', $idUser, $miniOrder, $nanoOrder);
            $stmtSave->execute();
            $stmtSave->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni rezimu karty selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
