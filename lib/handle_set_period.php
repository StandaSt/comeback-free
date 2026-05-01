<?php
// lib/handle_set_period.php * Verze: V2 * Aktualizace: 27.04.2026
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

    $normalizePeriodDateTime = static function (string $v): string {
        $v = trim(str_replace('T', ' ', $v));
        if ($v === '') {
            return '';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $v, $m) === 1) {
            $v = $m[1] . '-' . $m[2] . '-' . $m[3] . ' 06:00:00';
        } elseif (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$~', $v) === 1) {
            $v .= ':00';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$~', $v, $m) !== 1) {
            return '';
        }
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $h = (int)$m[4];
        $mi = (int)$m[5];
        $s = (int)$m[6];
        if (!checkdate($mo, $d, $y) || $h > 23 || $mi > 59 || $s > 59) {
            return '';
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $s);
    };

    $cbNowPeriod = new DateTimeImmutable('now');
    $cbCurrentWorkdayDate = $cbNowPeriod;
    if ((int)$cbNowPeriod->format('G') < 6) {
        $cbCurrentWorkdayDate = $cbCurrentWorkdayDate->modify('-1 day');
    }
    $today = $cbCurrentWorkdayDate->modify('-1 day')->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $defaultDo = $cbCurrentWorkdayDate->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $maxDo = $cbNowPeriod->format('Y-m-d H:i:s');

    $allowedModes = ['vcera', 'tyden', 'mesic', 'rok', 'manual'];
    $newMode = trim((string)($data['mode'] ?? 'manual'));
    if ($newMode === 'dnes') {
        $newMode = 'vcera';
    }
    if (!in_array($newMode, $allowedModes, true)) {
        $newMode = 'manual';
    }

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

        $currentOd = $normalizePeriodDateTime((string)($dbOd ?? ''));
        $currentDo = $normalizePeriodDateTime((string)($dbDo ?? ''));

        if ($currentOd === '') {
            $currentOd = $normalizePeriodDateTime((string)($_SESSION['cb_obdobi_od'] ?? ''));
        }
        if ($currentDo === '') {
            $currentDo = $normalizePeriodDateTime((string)($_SESSION['cb_obdobi_do'] ?? ''));
        }
        if ($currentOd === '') {
            $currentOd = $today;
        }
        if ($currentDo === '') {
            $currentDo = $defaultDo;
        }
        if ($currentOd > $maxDo) {
            $currentOd = $maxDo;
        }
        if ($currentDo > $maxDo) {
            $currentDo = $maxDo;
        }

        $newOd = $currentOd;
        $newDo = $currentDo;

        if ($hasOd) {
            $newOd = $normalizePeriodDateTime((string)($data['od'] ?? ''));
            if ($newOd === '') {
                http_response_code(422);
                echo json_encode(['ok' => false, 'err' => 'Neplatne od'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($newOd > $maxDo) {
                $newOd = $maxDo;
            }
        }

        if ($hasDo) {
            $newDo = $normalizePeriodDateTime((string)($data['do'] ?? ''));
            if ($newDo === '') {
                http_response_code(422);
                echo json_encode(['ok' => false, 'err' => 'Neplatne do'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($newDo > $maxDo) {
                $newDo = $maxDo;
            }
        }

        if ($newOd > $newDo) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Neplatne poradi dat'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($hasOd && $hasDo) {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_od = ?, obdobi_do = ?, obdobi_mode = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update failed');
            }
            $stmtUpd->bind_param('sssi', $newOd, $newDo, $newMode, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        } elseif ($hasOd) {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_od = ?, obdobi_mode = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update od failed');
            }
            $stmtUpd->bind_param('ssi', $newOd, $newMode, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtUpd = $conn->prepare('UPDATE user_set SET obdobi_do = ?, obdobi_mode = ? WHERE id_user = ?');
            if (!$stmtUpd) {
                throw new RuntimeException('prepare update do failed');
            }
            $stmtUpd->bind_param('ssi', $newDo, $newMode, $idUser);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        $_SESSION['cb_obdobi_od'] = $newOd;
        $_SESSION['cb_obdobi_do'] = $newDo;
        $_SESSION['cb_obdobi_mode'] = $newMode;

        echo json_encode([
            'ok' => true,
            'od' => $newOd,
            'do' => $newDo,
            'mode' => $newMode,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni obdobi selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
