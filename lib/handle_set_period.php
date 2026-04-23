<?php
// lib/handle_set_period.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_PERIOD'])
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

    $hasOd = array_key_exists('od', $data);
    $hasDo = array_key_exists('do', $data);
    if (!$hasOd && !$hasDo) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Chybi od/do'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isDate = static function (string $v): bool {
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $v)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        return checkdate($m, $d, $y);
    };

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    try {
        $conn = db();
        $stmtCur = $conn->prepare('SELECT obdobi_od, obdobi_do FROM user_set WHERE id_user = ? LIMIT 1');
        if (!$stmtCur) {
            throw new RuntimeException('prepare select failed');
        }
        $stmtCur->bind_param('i', $idUser);
        $stmtCur->execute();
        $stmtCur->bind_result($dbOd, $dbDo);
        $hasRow = $stmtCur->fetch();
        $stmtCur->close();

        if (!$hasRow) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Nenalezen user_set'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentOd = trim((string)($dbOd ?? ''));
        $currentDo = trim((string)($dbDo ?? ''));

        if (!$isDate($currentOd)) {
            $currentOd = (string)($_SESSION['cb_obdobi_od'] ?? '');
        }
        if (!$isDate($currentDo)) {
            $currentDo = (string)($_SESSION['cb_obdobi_do'] ?? '');
        }
        if (!$isDate($currentOd)) {
            $currentOd = $today;
        }
        if (!$isDate($currentDo)) {
            $currentDo = $today;
        }

        $newOd = $currentOd;
        $newDo = $currentDo;

        if ($hasOd) {
            $newOd = trim((string)($data['od'] ?? ''));
            if (!$isDate($newOd)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'err' => 'Neplatne od'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($newOd > $today) {
                $newOd = $today;
            }
        }

        if ($hasDo) {
            $newDo = trim((string)($data['do'] ?? ''));
            if (!$isDate($newDo)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'err' => 'Neplatne do'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($newDo > $today) {
                $newDo = $today;
            }
        }

        if ($newOd > $newDo) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Neplatne poradi dat'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($hasOd && $hasDo) {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_od = ?, obdobi_do = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update failed');
            }
            $stmtUpd->bind_param('ssi', $newOd, $newDo, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        } elseif ($hasOd) {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_od = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update od failed');
            }
            $stmtUpd->bind_param('si', $newOd, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_do = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update do failed');
            }
            $stmtUpd->bind_param('si', $newDo, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        $_SESSION['cb_obdobi_od'] = $newOd;
        $_SESSION['cb_obdobi_do'] = $newDo;

        echo json_encode([
            'ok' => true,
            'od' => $newOd,
            'do' => $newDo,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni obdobi selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
