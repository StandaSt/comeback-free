<?php
// lib/karty_admin_api.php * Verze: V6 * Aktualizace: 08.03.2026
declare(strict_types=1);

/*
 * Admin API: sprava karet + vyjimek.
 *
 * Endpoint (JSON):
 * - GET  ?action=list
 * - GET  ?action=users
 * - GET  ?action=vyjimky&id_user=123
 * - GET  ?action=vyjimky_log&id_user=&id_karta=&page=&per_page=
 * - POST { action:add|update|toggle, ... }
 * - POST { action:move, id_karta, direction=up|down }
 * - POST { action:set_vyjimka, id_user, id_karta, mode=role|allow|deny, poznamka? }
 *
 * Pozn.:
 * - jen pro admina
 * - bez mazani, pouze aktivace/deaktivace
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$cbUser = $_SESSION['cb_user'] ?? [];
$idRole = (int)($cbUser['id_role'] ?? 0);
$idAdminUser = (int)($cbUser['id_user'] ?? 0);
$isAdmin = ($idRole === 1);

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'Nemas opravneni.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$isKod = static function (string $v): bool {
    return (bool)preg_match('~^[a-z0-9_]{2,80}$~', $v);
};

$send = static function (int $code, array $payload): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $conn = db();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $action = (string)($_GET['action'] ?? 'list');

        if ($action === 'list') {
            $items = [];
            $res = $conn->query('
                SELECT id_karta, kod, nazev, soubor, min_role, poradi, aktivni, zalozeno
                FROM karty
                ORDER BY poradi ASC, id_karta ASC
            ');
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $items[] = [
                        'id_karta' => (int)$r['id_karta'],
                        'kod' => (string)$r['kod'],
                        'nazev' => (string)$r['nazev'],
                        'soubor' => (string)$r['soubor'],
                        'min_role' => (int)$r['min_role'],
                        'poradi' => (int)$r['poradi'],
                        'aktivni' => (int)$r['aktivni'],
                        'zalozeno' => (string)$r['zalozeno'],
                    ];
                }
                $res->free();
            }

            $send(200, ['ok' => true, 'items' => $items]);
        }

        if ($action === 'users') {
            $items = [];
            $res = $conn->query('
                SELECT id_user, jmeno, prijmeni, email, id_role, aktivni
                FROM user
                WHERE aktivni=1
                ORDER BY prijmeni ASC, jmeno ASC, id_user ASC
            ');
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $items[] = [
                        'id_user' => (int)$r['id_user'],
                        'jmeno' => (string)($r['jmeno'] ?? ''),
                        'prijmeni' => (string)($r['prijmeni'] ?? ''),
                        'email' => (string)($r['email'] ?? ''),
                        'id_role' => (int)($r['id_role'] ?? 0),
                        'aktivni' => (int)($r['aktivni'] ?? 0),
                    ];
                }
                $res->free();
            }

            $send(200, ['ok' => true, 'items' => $items]);
        }

        if ($action === 'vyjimky') {
            $idUser = (int)($_GET['id_user'] ?? 0);
            if ($idUser <= 0) {
                $send(422, ['ok' => false, 'err' => 'Neplatne ID uzivatele.']);
            }

            $stmtUser = $conn->prepare('SELECT id_role FROM user WHERE id_user=? LIMIT 1');
            if (!$stmtUser) {
                throw new RuntimeException('DB prepare selhal (user role).');
            }
            $stmtUser->bind_param('i', $idUser);
            $stmtUser->execute();
            $stmtUser->bind_result($idRoleUser);
            if (!$stmtUser->fetch()) {
                $stmtUser->close();
                $send(404, ['ok' => false, 'err' => 'Uzivatel nebyl nalezen.']);
            }
            $stmtUser->close();
            $idRoleUser = (int)$idRoleUser;

            $items = [];
            $stmt = $conn->prepare('
                SELECT
                    k.id_karta, k.kod, k.nazev, k.soubor, k.min_role, k.poradi, k.aktivni,
                    kv.akce, kv.aktivni
                FROM karty k
                LEFT JOIN karty_vyjimky kv
                  ON kv.id_karta = k.id_karta
                 AND kv.id_user = ?
                ORDER BY k.poradi ASC, k.id_karta ASC
            ');
            if (!$stmt) {
                throw new RuntimeException('DB prepare selhal (vyjimky list).');
            }
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $stmt->bind_result(
                $idKarta,
                $kod,
                $nazev,
                $soubor,
                $minRole,
                $poradi,
                $kartaAktivni,
                $akce,
                $vyjimkaAktivni
            );

            while ($stmt->fetch()) {
                $a = is_string($akce) ? $akce : null;
                $vAkt = ($vyjimkaAktivni === null) ? null : (int)$vyjimkaAktivni;
                $hasActiveOverride = ($vAkt === 1) && ($a === 'allow' || $a === 'deny');

                $roleAllowed = ($idRoleUser <= (int)$minRole);
                $override = 'role';
                $effective = $roleAllowed;

                if ($hasActiveOverride) {
                    $override = $a;
                    $effective = ($a === 'allow');
                }

                $items[] = [
                    'id_karta' => (int)$idKarta,
                    'kod' => (string)$kod,
                    'nazev' => (string)$nazev,
                    'soubor' => (string)$soubor,
                    'min_role' => (int)$minRole,
                    'poradi' => (int)$poradi,
                    'karta_aktivni' => (int)$kartaAktivni,
                    'role_allowed' => $roleAllowed ? 1 : 0,
                    'override' => $override,
                    'effective' => $effective ? 1 : 0,
                ];
            }
            $stmt->close();

            $send(200, [
                'ok' => true,
                'id_user' => $idUser,
                'id_role' => $idRoleUser,
                'items' => $items,
            ]);
        }

        if ($action === 'vyjimky_log') {
            $idUser = (int)($_GET['id_user'] ?? 0);
            $idKarta = (int)($_GET['id_karta'] ?? 0);
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 50);
            if (!in_array($perPage, [20, 50, 100], true)) {
                $perPage = 50;
            }
            if ($page < 1) {
                $page = 1;
            }

            $whereSql = ' WHERE 1=1 ';
            $whereTypes = '';
            $whereVals = [];

            if ($idUser > 0) {
                $whereSql .= ' AND l.id_user_cil = ?';
                $whereTypes .= 'i';
                $whereVals[] = $idUser;
            }
            if ($idKarta > 0) {
                $whereSql .= ' AND l.id_karta = ?';
                $whereTypes .= 'i';
                $whereVals[] = $idKarta;
            }

            // Nejprve celkovy pocet zaznamu pro pager.
            $sqlCount = 'SELECT COUNT(*) AS cnt FROM karty_vyjimky_log l' . $whereSql;
            $stmtCount = $conn->prepare($sqlCount);
            if (!$stmtCount) {
                throw new RuntimeException('DB prepare selhal (vyjimky log count).');
            }
            if ($whereTypes !== '') {
                $bindCount = [$whereTypes];
                foreach ($whereVals as $i => $v) {
                    $bindCount[] = &$whereVals[$i];
                }
                call_user_func_array([$stmtCount, 'bind_param'], $bindCount);
            }
            $stmtCount->execute();
            $resCount = $stmtCount->get_result();
            $rowCount = $resCount ? $resCount->fetch_assoc() : null;
            $total = (int)($rowCount['cnt'] ?? 0);
            if ($resCount) {
                $resCount->free();
            }
            $stmtCount->close();

            $pages = max(1, (int)ceil($total / $perPage));
            if ($page > $pages) {
                $page = $pages;
            }
            $offset = ($page - 1) * $perPage;

            $sql = '
                SELECT
                    l.id_log,
                    l.id_user_cil,
                    l.id_karta,
                    l.stara_akce,
                    l.stara_aktivni,
                    l.nova_akce,
                    l.nova_aktivni,
                    l.provedl_id_user,
                    l.kdy,
                    l.poznamka,
                    u1.jmeno AS cil_jmeno,
                    u1.prijmeni AS cil_prijmeni,
                    u1.email AS cil_email,
                    u2.jmeno AS provedl_jmeno,
                    u2.prijmeni AS provedl_prijmeni,
                    u2.email AS provedl_email,
                    k.kod AS karta_kod,
                    k.nazev AS karta_nazev
                FROM karty_vyjimky_log l
                LEFT JOIN user u1 ON u1.id_user = l.id_user_cil
                LEFT JOIN user u2 ON u2.id_user = l.provedl_id_user
                LEFT JOIN karty k ON k.id_karta = l.id_karta
            ' . $whereSql . '
                ORDER BY l.kdy DESC, l.id_log DESC
                LIMIT ?, ?
            ';
            $types = $whereTypes . 'ii';
            $vals = $whereVals;
            $vals[] = $offset;
            $vals[] = $perPage;

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('DB prepare selhal (vyjimky log).');
            }

            $bind = [$types];
            foreach ($vals as $i => $v) {
                $bind[] = &$vals[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);

            $stmt->execute();
            $res = $stmt->get_result();
            $items = [];

            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $items[] = [
                        'id_log' => (int)$r['id_log'],
                        'id_user_cil' => (int)$r['id_user_cil'],
                        'id_karta' => (int)$r['id_karta'],
                        'stara_akce' => isset($r['stara_akce']) ? (string)$r['stara_akce'] : null,
                        'stara_aktivni' => isset($r['stara_aktivni']) ? (int)$r['stara_aktivni'] : null,
                        'nova_akce' => isset($r['nova_akce']) ? (string)$r['nova_akce'] : null,
                        'nova_aktivni' => isset($r['nova_aktivni']) ? (int)$r['nova_aktivni'] : null,
                        'provedl_id_user' => (int)$r['provedl_id_user'],
                        'kdy' => (string)$r['kdy'],
                        'poznamka' => isset($r['poznamka']) ? (string)$r['poznamka'] : '',
                        'cil_jmeno' => trim((string)($r['cil_prijmeni'] ?? '') . ' ' . (string)($r['cil_jmeno'] ?? '')),
                        'cil_email' => (string)($r['cil_email'] ?? ''),
                        'provedl_jmeno' => trim((string)($r['provedl_prijmeni'] ?? '') . ' ' . (string)($r['provedl_jmeno'] ?? '')),
                        'provedl_email' => (string)($r['provedl_email'] ?? ''),
                        'karta_kod' => (string)($r['karta_kod'] ?? ''),
                        'karta_nazev' => (string)($r['karta_nazev'] ?? ''),
                    ];
                }
                $res->free();
            }
            $stmt->close();

            $send(200, [
                'ok' => true,
                'items' => $items,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $pages,
            ]);
        }

        $send(400, ['ok' => false, 'err' => 'Neznama akce.']);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        $send(405, ['ok' => false, 'err' => 'Metoda neni povolena.']);
    }

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $send(400, ['ok' => false, 'err' => 'Neplatny JSON.']);
    }

    $action = (string)($data['action'] ?? '');

    if ($action === 'add') {
        $kod = trim((string)($data['kod'] ?? ''));
        $nazev = trim((string)($data['nazev'] ?? ''));
        $soubor = trim((string)($data['soubor'] ?? ''));
        $minRole = (int)($data['min_role'] ?? 9);
        $poradi = (int)($data['poradi'] ?? 999);

        if (!$isKod($kod) || $nazev === '' || $soubor === '') {
            $send(422, ['ok' => false, 'err' => 'Neplatna vstupni data.']);
        }

        $stmt = $conn->prepare('
            INSERT INTO karty (kod, nazev, soubor, min_role, poradi, aktivni, zalozeno)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ');
        if (!$stmt) {
            throw new RuntimeException('DB prepare selhal.');
        }
        $stmt->bind_param('sssii', $kod, $nazev, $soubor, $minRole, $poradi);
        $stmt->execute();
        $stmt->close();

        $send(200, ['ok' => true]);
    }

    if ($action === 'update') {
        $idKarta = (int)($data['id_karta'] ?? 0);
        $nazev = trim((string)($data['nazev'] ?? ''));
        $soubor = trim((string)($data['soubor'] ?? ''));
        $minRole = (int)($data['min_role'] ?? 9);
        $poradi = (int)($data['poradi'] ?? 999);

        if ($idKarta <= 0 || $nazev === '' || $soubor === '') {
            $send(422, ['ok' => false, 'err' => 'Neplatna vstupni data.']);
        }

        $stmt = $conn->prepare('
            UPDATE karty
            SET nazev=?, soubor=?, min_role=?, poradi=?
            WHERE id_karta=?
            LIMIT 1
        ');
        if (!$stmt) {
            throw new RuntimeException('DB prepare selhal.');
        }
        $stmt->bind_param('ssiii', $nazev, $soubor, $minRole, $poradi, $idKarta);
        $stmt->execute();
        $stmt->close();

        $send(200, ['ok' => true]);
    }

    if ($action === 'toggle') {
        $idKarta = (int)($data['id_karta'] ?? 0);
        $aktivni = (int)($data['aktivni'] ?? 0);
        $aktivni = ($aktivni === 1) ? 1 : 0;

        if ($idKarta <= 0) {
            $send(422, ['ok' => false, 'err' => 'Neplatne ID karty.']);
        }

        $stmt = $conn->prepare('
            UPDATE karty
            SET aktivni=?
            WHERE id_karta=?
            LIMIT 1
        ');
        if (!$stmt) {
            throw new RuntimeException('DB prepare selhal.');
        }
        $stmt->bind_param('ii', $aktivni, $idKarta);
        $stmt->execute();
        $stmt->close();

        $send(200, ['ok' => true]);
    }

    if ($action === 'move') {
        $idKarta = (int)($data['id_karta'] ?? 0);
        $direction = trim((string)($data['direction'] ?? ''));
        if ($idKarta <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $send(422, ['ok' => false, 'err' => 'Neplatna vstupni data.']);
        }

        $inTx = false;
        try {
            $conn->begin_transaction();
            $inTx = true;

            $stmtCur = $conn->prepare('
                SELECT id_karta, poradi
                FROM karty
                WHERE id_karta=?
                LIMIT 1
                FOR UPDATE
            ');
            if (!$stmtCur) {
                throw new RuntimeException('DB prepare selhal (move current).');
            }
            $stmtCur->bind_param('i', $idKarta);
            $stmtCur->execute();
            $stmtCur->bind_result($curId, $curPoradi);
            if (!$stmtCur->fetch()) {
                $stmtCur->close();
                $send(404, ['ok' => false, 'err' => 'Karta nebyla nalezena.']);
            }
            $stmtCur->close();
            $curId = (int)$curId;
            $curPoradi = (int)$curPoradi;

            if ($direction === 'up') {
                $stmtNbr = $conn->prepare('
                    SELECT id_karta, poradi
                    FROM karty
                    WHERE (poradi < ?) OR (poradi = ? AND id_karta < ?)
                    ORDER BY poradi DESC, id_karta DESC
                    LIMIT 1
                    FOR UPDATE
                ');
            } else {
                $stmtNbr = $conn->prepare('
                    SELECT id_karta, poradi
                    FROM karty
                    WHERE (poradi > ?) OR (poradi = ? AND id_karta > ?)
                    ORDER BY poradi ASC, id_karta ASC
                    LIMIT 1
                    FOR UPDATE
                ');
            }
            if (!$stmtNbr) {
                throw new RuntimeException('DB prepare selhal (move neighbor).');
            }
            $stmtNbr->bind_param('iii', $curPoradi, $curPoradi, $curId);
            $stmtNbr->execute();
            $stmtNbr->bind_result($nbrId, $nbrPoradi);
            $hasNbr = $stmtNbr->fetch();
            $stmtNbr->close();

            if (!$hasNbr) {
                $conn->commit();
                $send(200, ['ok' => true, 'moved' => 0]);
            }

            $nbrId = (int)$nbrId;
            $nbrPoradi = (int)$nbrPoradi;

            // Prohozeni poradi dvou sousednich zaznamu.
            $stmtSwap1 = $conn->prepare('UPDATE karty SET poradi=? WHERE id_karta=? LIMIT 1');
            if (!$stmtSwap1) {
                throw new RuntimeException('DB prepare selhal (swap1).');
            }
            $stmtSwap1->bind_param('ii', $nbrPoradi, $curId);
            $stmtSwap1->execute();
            $stmtSwap1->close();

            $stmtSwap2 = $conn->prepare('UPDATE karty SET poradi=? WHERE id_karta=? LIMIT 1');
            if (!$stmtSwap2) {
                throw new RuntimeException('DB prepare selhal (swap2).');
            }
            $stmtSwap2->bind_param('ii', $curPoradi, $nbrId);
            $stmtSwap2->execute();
            $stmtSwap2->close();

            $conn->commit();
            $send(200, ['ok' => true, 'moved' => 1]);
        } catch (Throwable $e) {
            if ($inTx) {
                $conn->rollback();
            }
            throw $e;
        }
    }

    if ($action === 'set_vyjimka') {
        $idUser = (int)($data['id_user'] ?? 0);
        $idKarta = (int)($data['id_karta'] ?? 0);
        $mode = trim((string)($data['mode'] ?? 'role'));
        $poznamka = trim((string)($data['poznamka'] ?? ''));
        if (mb_strlen($poznamka) > 255) {
            $poznamka = mb_substr($poznamka, 0, 255);
        }

        if ($idUser <= 0 || $idKarta <= 0 || !in_array($mode, ['role', 'allow', 'deny'], true)) {
            $send(422, ['ok' => false, 'err' => 'Neplatna vstupni data.']);
        }
        if ($idAdminUser <= 0) {
            $send(422, ['ok' => false, 'err' => 'Chybi ID admin uzivatele.']);
        }

        $inTx = false;
        try {
            $conn->begin_transaction();
            $inTx = true;

            $stmtExistsUser = $conn->prepare('SELECT id_user FROM user WHERE id_user=? LIMIT 1');
            if (!$stmtExistsUser) {
                throw new RuntimeException('DB prepare selhal (exist user).');
            }
            $stmtExistsUser->bind_param('i', $idUser);
            $stmtExistsUser->execute();
            $stmtExistsUser->store_result();
            $userExists = ($stmtExistsUser->num_rows > 0);
            $stmtExistsUser->close();
            if (!$userExists) {
                throw new RuntimeException('Uzivatel neexistuje.');
            }

            $stmtExistsCard = $conn->prepare('SELECT id_karta FROM karty WHERE id_karta=? LIMIT 1');
            if (!$stmtExistsCard) {
                throw new RuntimeException('DB prepare selhal (exist karta).');
            }
            $stmtExistsCard->bind_param('i', $idKarta);
            $stmtExistsCard->execute();
            $stmtExistsCard->store_result();
            $cardExists = ($stmtExistsCard->num_rows > 0);
            $stmtExistsCard->close();
            if (!$cardExists) {
                throw new RuntimeException('Karta neexistuje.');
            }

            $stmtSel = $conn->prepare('
                SELECT id_vyjimka, akce, aktivni
                FROM karty_vyjimky
                WHERE id_user=? AND id_karta=?
                LIMIT 1
                FOR UPDATE
            ');
            if (!$stmtSel) {
                throw new RuntimeException('DB prepare selhal (select vyjimka).');
            }
            $stmtSel->bind_param('ii', $idUser, $idKarta);
            $stmtSel->execute();
            $stmtSel->bind_result($idVyjimka, $oldAkceDb, $oldAktivniDb);

            $hasRow = $stmtSel->fetch();
            $stmtSel->close();

            $oldAkce = null;
            $oldAktivni = null;
            if ($hasRow) {
                $oldAkce = is_string($oldAkceDb) ? $oldAkceDb : null;
                $oldAktivni = (int)$oldAktivniDb;
            }

            $newAkce = $oldAkce;
            $newAktivni = $oldAktivni;

            if ($mode === 'role') {
                if ($hasRow) {
                    $stmtUpd = $conn->prepare('
                        UPDATE karty_vyjimky
                        SET aktivni=0
                        WHERE id_vyjimka=?
                        LIMIT 1
                    ');
                    if (!$stmtUpd) {
                        throw new RuntimeException('DB prepare selhal (disable vyjimka).');
                    }
                    $stmtUpd->bind_param('i', $idVyjimka);
                    $stmtUpd->execute();
                    $stmtUpd->close();

                    $newAkce = $oldAkce;
                    $newAktivni = 0;
                } else {
                    $newAkce = null;
                    $newAktivni = null;
                }
            } else {
                if ($hasRow) {
                    $stmtUpd = $conn->prepare('
                        UPDATE karty_vyjimky
                        SET akce=?, aktivni=1
                        WHERE id_vyjimka=?
                        LIMIT 1
                    ');
                    if (!$stmtUpd) {
                        throw new RuntimeException('DB prepare selhal (update vyjimka).');
                    }
                    $stmtUpd->bind_param('si', $mode, $idVyjimka);
                    $stmtUpd->execute();
                    $stmtUpd->close();
                } else {
                    $stmtIns = $conn->prepare('
                        INSERT INTO karty_vyjimky (id_user, id_karta, akce, aktivni, zalozeno)
                        VALUES (?, ?, ?, 1, NOW())
                    ');
                    if (!$stmtIns) {
                        throw new RuntimeException('DB prepare selhal (insert vyjimka).');
                    }
                    $stmtIns->bind_param('iis', $idUser, $idKarta, $mode);
                    $stmtIns->execute();
                    $stmtIns->close();
                }

                $newAkce = $mode;
                $newAktivni = 1;
            }

            $changed = ($oldAkce !== $newAkce) || ($oldAktivni !== $newAktivni);
            if ($changed) {
                $stmtLog = $conn->prepare('
                    INSERT INTO karty_vyjimky_log
                    (id_user_cil, id_karta, stara_akce, stara_aktivni, nova_akce, nova_aktivni, provedl_id_user, kdy, poznamka)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ');
                if (!$stmtLog) {
                    throw new RuntimeException('DB prepare selhal (log vyjimka).');
                }
                $stmtLog->bind_param(
                    'iisisiis',
                    $idUser,
                    $idKarta,
                    $oldAkce,
                    $oldAktivni,
                    $newAkce,
                    $newAktivni,
                    $idAdminUser,
                    $poznamka
                );
                $stmtLog->execute();
                $stmtLog->close();
            }

            $conn->commit();
            $send(200, ['ok' => true, 'changed' => $changed ? 1 : 0]);
        } catch (Throwable $e) {
            if ($inTx) {
                $conn->rollback();
            }
            throw $e;
        }
    }

    $send(400, ['ok' => false, 'err' => 'Neznama akce.']);
} catch (Throwable $e) {
    $send(500, ['ok' => false, 'err' => $e->getMessage()]);
}

// lib/karty_admin_api.php * Verze: V6 * Aktualizace: 08.03.2026 * Pocet radku: 707
// Konec souboru
