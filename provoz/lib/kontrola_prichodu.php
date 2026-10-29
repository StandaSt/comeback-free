<?php
declare(strict_types=1);
/* Účel: Společná kontrola příchodů pizzařů od 10:00; pouze čte zveřejněné směny, potvrzené PC a přihlášení. */
require_once __DIR__ . '/../../common/lib/smeny_verejny_zdroj.php';
require_once __DIR__ . '/../../common/lib/pobockove_pc.php';

/** Každý pizzař od 10:00 má vlastní povinnost, bez ohledu na přihlášení kolegy; odhlášení příchod neruší. */
function cb_prichody_vyhodnot(array $shifts, array $logins, array $confirmedBranches): array
{
    $branches = [];
    foreach ($shifts as $shift) {
        $branch = (int)$shift['id_pob'];
        $user = (int)$shift['id_user'];
        if (!isset($branches[$branch])) {
            $branches[$branch] = ['id_pob' => $branch, 'nazev' => (string)$shift['pobocka'],
                'overena' => isset($confirmedBranches[$branch]), 'prihlaseni' => [], 'neprihlaseni' => []];
        }
        $person = ['id_user' => $user, 'jmeno' => trim((string)$shift['jmeno'] . ' ' . (string)$shift['prijmeni'])];
        if ($user <= 0 || $person['jmeno'] === '') {
            throw new RuntimeException('Směna od 10:00 na pobočce ' . $shift['pobocka'] . ' nemá dohledaný účet nebo jméno zaměstnance.');
        }
        $login = $logins[$branch . ':' . $user] ?? null;
        $person['prihlasen'] = $login;
        $branches[$branch][$login !== null ? 'prihlaseni' : 'neprihlaseni'][$user] = $person;
    }
    return $branches;
}

/** Rozsah je dnešní provozní den od 06:00 do okamžiku kontroly; telefon se vyloučí i při shodě profilu. */
function cb_prichody_zkontroluj(mysqli $db, DateTimeImmutable $now): array
{
    $now = $now->setTimezone(new DateTimeZone('Europe/Prague'));
    if (!cb_pobockove_pc_schema($db)) {
        throw new RuntimeException('Chybí tabulka pobockove_pc. Nejdříve spusťte připravené SQL a potvrďte PC poboček.');
    }
    $date = $now->format('Y-m-d');
    $from = $date . ' 06:00:00';
    $until = $now->format('Y-m-d H:i:s');
    $stmt = $db->prepare('SELECT DISTINCT s.id_pob, s.id_user, p.nazev pobocka, o.jmeno, o.prijmeni
        FROM (' . cb_smeny_verejny_zdroj_sql() . ') s
        JOIN pobocka p ON p.id_pob=s.id_pob AND p.aktivni=1
        LEFT JOIN hr_osobni_udaje o ON o.id_person=s.id_person AND o.platny=1
        WHERE s.id_slot=1 AND s.datum=? AND s.cas_od=\'10:00:00\'
        ORDER BY p.nazev, o.prijmeni, o.jmeno, s.id_user');
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $shifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $confirmed = [];
    $result = $db->query('SELECT DISTINCT id_pob FROM pobockove_pc WHERE stav=1');
    while ($row = $result->fetch_assoc()) { $confirmed[(int)$row['id_pob']] = true; }
    $stmt = $db->prepare('SELECT pc.id_pob, l.id_user, MIN(l.kdy) prihlasen
        FROM user_login l JOIN pobockove_pc pc ON pc.pc_hash=l.pc_hash AND pc.stav=1
        JOIN user_spy spy ON spy.id_login=l.id_login
        WHERE l.akce=1 AND l.kdy>=? AND l.kdy<=?
            AND spy.user_agent REGEXP \'Windows NT|Macintosh|X11|CrOS\'
            AND spy.user_agent NOT REGEXP \'Mobile|Android|iPhone|iPad|iPod|Tablet\'
        GROUP BY pc.id_pob, l.id_user');
    $stmt->bind_param('ss', $from, $until);
    $stmt->execute();
    $logins = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $logins[(int)$row['id_pob'] . ':' . (int)$row['id_user']] = (string)$row['prihlasen'];
    }
    return cb_prichody_vyhodnot($shifts, $logins, $confirmed);
}

