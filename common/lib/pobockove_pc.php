<?php
declare(strict_types=1);
/* Účel: Rozpoznává kandidáta pobočkového PC z historie reportů a uchovává jeho výslovné potvrzení či zamítnutí. */

/** Telefon ani tablet nesmí nabídnout potvrzení nebo doložit příchod. Prázdný či neznámý klient se neuznává. */
function cb_pobockove_pc_desktop(string $agent): bool
{
    return preg_match('~Windows NT|Macintosh|X11|CrOS~i', $agent) === 1
        && preg_match('~Mobile|Android|iPhone|iPad|iPod|Tablet~i', $agent) !== 1;
}

/** Před ručním spuštěním SQL zůstává běžný IS funkční; za chybějící tabulku se nesmí vydávat výpadek DB. */
function cb_pobockove_pc_schema(mysqli $db): bool
{
    $result = $db->query("SHOW TABLES LIKE 'pobockove\\_pc'");
    return $result->num_rows > 0;
}

/** Identita profilu musí odpovídat cookie, aktuální session i úspěšnému přihlášení. Klient neposílá vlastní hash. */
function cb_pobockove_pc_hash(mysqli $db): string
{
    $token = cb_pc_cookie_token(false);
    if (empty($_SESSION['login_ok']) || $token === ''
        || !hash_equals((string)($_SESSION['cb_pc_token'] ?? ''), $token)
        || !cb_pobockove_pc_desktop((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))
        || (string)($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '') === '?1') {
        return '';
    }
    $hash = hash('sha256', $token);
    $stmt = $db->prepare('SELECT 1 FROM user_login WHERE id_login=? AND id_user=? AND akce=1 AND pc_hash=UNHEX(?)');
    $login = (int)($_SESSION['cb_id_login'] ?? 0);
    $user = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    $stmt->bind_param('iis', $login, $user, $hash);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0 ? $hash : '';
}

/** Alespoň tři různé reportové dny a 70% převaha jediné pobočky omezí nabídky na obvyklá pracoviště. */
function cb_pobockove_pc_vyber_kandidata(array $rows): ?array
{
    $total = array_sum(array_column($rows, 'dnu'));
    usort($rows, static fn(array $a, array $b): int => (int)$b['dnu'] <=> (int)$a['dnu']);
    $first = $rows[0] ?? null;
    if (!$first || (int)$first['dnu'] < 3 || (int)$first['dnu'] < 0.7 * $total) {
        return null;
    }
    return $first;
}

/** Nejbližší předcházející přihlášení autora reportu je pouze podklad otázky, nikdy automatické potvrzení PC. */
function cb_pobockove_pc_kandidat(mysqli $db, string $hash): ?array
{
    $stmt = $db->prepare('SELECT id_pob, stav FROM pobockove_pc WHERE pc_hash=UNHEX(?)');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stored = $stmt->get_result()->fetch_assoc();
    if ($stored && (int)$stored['stav'] !== 0) {
        return null;
    }
    if ($stored) {
        $stmt = $db->prepare('SELECT id_pob, nazev FROM pobocka WHERE id_pob=? AND aktivni=1');
        $stmt->bind_param('i', $stored['id_pob']);
    } else {
        $stmt = $db->prepare('SELECT p.id_pob, p.nazev, COUNT(DISTINCT r.datum_reportu) dnu
            FROM reporty_is r
            JOIN pobocka p ON p.id_pob=r.id_pob AND p.aktivni=1
            JOIN user_login l ON l.id_login=(SELECT x.id_login FROM user_login x
                WHERE x.id_user=r.zadal AND x.akce=1 AND x.kdy<=r.zadano
                    AND x.kdy>=DATE_SUB(r.zadano, INTERVAL 12 HOUR)
                ORDER BY x.kdy DESC, x.id_login DESC LIMIT 1)
            JOIN user_spy spy ON spy.id_login=l.id_login
            WHERE r.zdroj=2 AND r.platny=1 AND r.zadano>=DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND l.pc_hash=UNHEX(?)
                AND spy.user_agent REGEXP \'Windows NT|Macintosh|X11|CrOS\'
                AND spy.user_agent NOT REGEXP \'Mobile|Android|iPhone|iPad|iPod|Tablet\'
            GROUP BY p.id_pob, p.nazev');
        $stmt->bind_param('s', $hash);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return $stored ? ($rows[0] ?? null) : cb_pobockove_pc_vyber_kandidata($rows);
}

/** Odpověď a uživatelský audit jsou jedna transakce; opakovaný POST rozhodnutí nepřepisuje. */
function cb_pobockove_pc_potvrd(mysqli $db, string $hash, int $branch, bool $yes): void
{
    require_once __DIR__ . '/uloz_akci.php';
    $db->begin_transaction();
    try {
        $candidate = cb_pobockove_pc_kandidat($db, $hash);
        if (!$candidate || (int)$candidate['id_pob'] !== $branch) {
            throw new CbUserVisibleException('Nabídka potvrzení již není aktuální. Obnovte stránku.');
        }
        $state = $yes ? 1 : 2;
        $user = (int)$_SESSION['cb_user']['id_user'];
        $stmt = $db->prepare('INSERT INTO pobockove_pc (pc_hash, id_pob, stav, id_user_potvrdil, potvrzeno)
            VALUES (UNHEX(?), ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE id_pobockove_pc=LAST_INSERT_ID(id_pobockove_pc),
                id_pob=IF(stav=0, VALUES(id_pob), id_pob),
                id_user_potvrdil=IF(stav=0, VALUES(id_user_potvrdil), id_user_potvrdil),
                potvrzeno=IF(stav=0, VALUES(potvrzeno), potvrzeno), stav=IF(stav=0, VALUES(stav), stav)');
        $stmt->bind_param('siii', $hash, $branch, $state, $user);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new CbUserVisibleException('Na tomto počítači již bylo odpovězeno. Obnovte stránku.');
        }
        $id = (int)$db->insert_id;
        if (!cb_user_akce_zapis([
            'id_modul' => 1, 'id_user_akce_typ' => 12, 'objekt' => 'pobockove_pc', 'id_objektu' => $id,
            'pole' => 'stav', 'hodnota_old' => 'čeká na potvrzení',
            'hodnota_new' => $yes ? 'potvrzeno' : 'zamítnuto', 'zdroj' => 'pobockove_pc_potvrzeni',
            'detail' => ['id_pob' => $branch, 'pobocka' => (string)$candidate['nazev']],
        ])) {
            throw new RuntimeException('Potvrzení pobočkového PC se nepodařilo zapsat do historie akcí.');
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
