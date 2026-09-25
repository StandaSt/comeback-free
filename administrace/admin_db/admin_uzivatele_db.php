<?php
declare(strict_types=1);

/* Datové operace pro správu lokálních uživatelů Administrace. */

function cb_admin_uzivatele_sloty_text(string $rawSlots): string
{
    $labels = [];
    foreach (explode('||', $rawSlots) as $rawSlot) {
        $slot = trim($rawSlot);
        if ($slot === '') {
            continue;
        }
        $labels[$slot] = true;
    }

    return implode(', ', array_keys($labels));
}

function cb_admin_uzivatele_csrf_token(): string
{
    $token = $_SESSION['cb_admin_uzivatele_csrf'] ?? null;
    if (!is_string($token) || strlen($token) < 32) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['cb_admin_uzivatele_csrf'] = $token;
    }
    return $token;
}

function cb_admin_uzivatele_csrf_over(array $post): void
{
    $token = (string)($post['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(cb_admin_uzivatele_csrf_token(), $token)) {
        throw new CbUserVisibleException('Platnost formuláře vypršela. Obnovte stránku a zkuste akci znovu.');
    }
}

function cb_admin_uzivatele_ciselniky(mysqli $db): array
{
    $out = ['firmy' => [], 'role' => [], 'sloty' => [], 'pobocky' => []];
    $queries = [
        'firmy' => 'SELECT id_firma AS id, obchodni_jmeno AS nazev FROM firma WHERE aktivni=1 ORDER BY obchodni_jmeno',
        'role' => 'SELECT id_role AS id, role AS nazev FROM cis_role WHERE aktivni=1 ORDER BY role',
        'sloty' => 'SELECT id_slot AS id, slot AS nazev FROM cis_slot ORDER BY id_slot',
        'pobocky' => 'SELECT id_pob, id_firma, nazev FROM pobocka WHERE aktivni=1 ORDER BY nazev',
    ];
    foreach ($queries as $key => $sql) {
        $res = $db->query($sql);
        while ($row = $res->fetch_assoc()) {
            $out[$key][] = $row;
        }
        $res->free();
    }
    return $out;
}

function cb_admin_uzivatele_filtry(array $source): array
{
    $raw = is_array($source['usr_f'] ?? null) ? $source['usr_f'] : [];
    $text = static fn (string $key): string => mb_substr(trim((string)($raw[$key] ?? '')), 0, 120, 'UTF-8');
    return [
        'id' => $text('id'), 'uzivatel' => $text('uzivatel'), 'kontakt' => $text('kontakt'),
        'firma' => (string)(int)($raw['firma'] ?? 0), 'role' => (string)(int)($raw['role'] ?? 0),
        'slot' => (string)max(-1, (int)($raw['slot'] ?? -1)),
        'pobocka' => (string)(int)($raw['pobocka'] ?? -1),
        'stav' => in_array((string)($raw['stav'] ?? 'vse'), ['aktivni', 'neaktivni', 'vse'], true) ? (string)($raw['stav'] ?? 'vse') : 'vse',
    ];
}

function cb_admin_uzivatele_nacti(mysqli $db, array $source): array
{
    $filters = cb_admin_uzivatele_filtry($source);
    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($source['usr_per'] ?? 50);
    if (!in_array($perPage, $perOptions, true)) { $perPage = 50; }
    $page = max(1, (int)($source['usr_p'] ?? 1));
    $sortMap = ['id' => 'u.id_user', 'uzivatel' => 'ou.prijmeni,ou.jmeno', 'kontakt' => 'u.email', 'firma' => 'f.obchodni_jmeno', 'role' => 'role', 'slot' => 'slot', 'pobocky' => 'pobocka_main', 'stav' => 'hp.aktivni'];
    $sort = (string)($source['usr_sort'] ?? 'uzivatel');
    if (!isset($sortMap[$sort])) { $sort = 'uzivatel'; }
    $dir = strtolower((string)($source['usr_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    $where = [];
    $params = [];
    $addLike = static function (string $field, string $value) use (&$where, &$params): void { if ($value !== '') { $where[] = $field . ' LIKE ?'; $params[] = '%' . $value . '%'; } };
    $addLike('CAST(u.id_user AS CHAR)', $filters['id']);
    $addLike('CONCAT_WS(" ",ou.jmeno,ou.prijmeni)', $filters['uzivatel']);
    $addLike('CONCAT_WS(" ",u.email,(SELECT t.telefon FROM hr_telefon t WHERE t.id_person=hp.id_person AND t.platny=1 AND t.hlavni=1 ORDER BY t.id_telefon DESC LIMIT 1))', $filters['kontakt']);
    if ((int)$filters['firma'] > 0) { $where[] = 'hp.id_firma=?'; $params[] = (int)$filters['firma']; }
    if ((int)$filters['role'] > 0) { $where[] = 'EXISTS (SELECT 1 FROM hr_pristupovy_profil prf WHERE prf.id_person=hp.id_person AND prf.id_role=?)'; $params[] = (int)$filters['role']; }
    if ((int)$filters['slot'] >= 0) { $where[] = 'EXISTS (SELECT 1 FROM hr_zarazeni zf WHERE zf.id_person=hp.id_person AND zf.id_slot=? AND zf.platny=1)'; $params[] = (int)$filters['slot']; }
    if ((int)$filters['pobocka'] >= 0) { $where[] = '(hp.pristup_vsechny_pobocky=1 OR EXISTS (SELECT 1 FROM hr_pracoviste pf WHERE pf.id_person=hp.id_person AND pf.id_pob=? AND pf.platny=1))'; $params[] = (int)$filters['pobocka']; }
    if ($filters['stav'] === 'aktivni') { $where[] = 'hp.aktivni=1'; }
    if ($filters['stav'] === 'neaktivni') { $where[] = 'hp.aktivni=0'; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $fromSql = ' FROM user u INNER JOIN hr_person hp ON hp.id_person=u.id_user AND hp.id_user=u.id_user LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(ou2.id_osobni_udaje) FROM hr_osobni_udaje ou2 WHERE ou2.id_person=hp.id_person AND ou2.platny=1) LEFT JOIN firma f ON f.id_firma=hp.id_firma';
    $count = $db->prepare('SELECT COUNT(*) AS c' . $fromSql . $whereSql);
    if ($params !== []) { $types = implode('', array_map(static fn ($value): string => is_int($value) ? 'i' : 's', $params)); $bind = [&$types]; foreach ($params as $index => $value) { $bind[] = &$params[$index]; } call_user_func_array([$count, 'bind_param'], $bind); }
    $count->execute(); $total = (int)($count->get_result()->fetch_assoc()['c'] ?? 0); $count->close();
    $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare('
        SELECT u.id_user,ou.jmeno,ou.prijmeni,u.email,
               (SELECT t.telefon FROM hr_telefon t WHERE t.id_person=hp.id_person AND t.platny=1 AND t.hlavni=1 ORDER BY t.id_telefon DESC LIMIT 1) AS telefon,
               hp.aktivni,f.obchodni_jmeno,
               COALESCE((
                   SELECT cr.role
                   FROM hr_pristupovy_profil pr_top
                   INNER JOIN cis_role cr ON cr.id_role=pr_top.id_role
                   WHERE pr_top.id_person=hp.id_person AND cr.aktivni=1
                   ORDER BY pr_top.id_role ASC
                   LIMIT 1
               ), "") AS role,
               COALESCE((
                   SELECT GROUP_CONCAT(DISTINCT cs.slot ORDER BY z.id_slot SEPARATOR "||")
                   FROM hr_zarazeni z
                   INNER JOIN cis_slot cs ON cs.id_slot=z.id_slot
                   WHERE z.id_person=hp.id_person AND z.platny=1
               ), "") AS slot,
               (SELECT p.nazev FROM hr_pracoviste prac INNER JOIN pobocka p ON p.id_pob=prac.id_pob WHERE prac.id_person=hp.id_person AND prac.platny=1 AND prac.hlavni=1 ORDER BY prac.id_pracoviste DESC LIMIT 1) AS pobocka_main,
               CASE
                   WHEN hp.pristup_vsechny_pobocky=1 THEN (
                       SELECT COUNT(*) FROM pobocka p_all WHERE p_all.id_firma=hp.id_firma AND p_all.aktivni=1
                   )
                   ELSE (SELECT COUNT(DISTINCT prac.id_pob) FROM hr_pracoviste prac WHERE prac.id_person=hp.id_person AND prac.platny=1)
               END AS pobocky_pocet,
               CASE WHEN u.heslo_hash IS NULL OR u.heslo_hash="" THEN 0 ELSE 1 END AS ma_heslo
        ' . $fromSql . '
        ' . $whereSql . '
        ORDER BY ' . $sortMap[$sort] . ' ' . strtoupper($dir) . ', u.id_user DESC LIMIT ? OFFSET ?
    ');
    $bindParams = $params; $bindParams[] = $perPage; $bindParams[] = $offset;
    $types = implode('', array_map(static fn ($value): string => is_int($value) ? 'i' : 's', $bindParams)); $bind = [&$types]; foreach ($bindParams as $index => $value) { $bind[] = &$bindParams[$index]; } call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute(); $res=$stmt->get_result(); $rows=[];
    while ($row=$res->fetch_assoc()) {
        $row['slot'] = cb_admin_uzivatele_sloty_text((string)($row['slot'] ?? ''));
        $rows[]=$row;
    } $stmt->close();
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage,'per_options'=>$perOptions,'filters'=>$filters,'sort'=>$sort,'dir'=>$dir,'first_row'=>$total === 0 ? 0 : $offset + 1,'last_row'=>min($offset + $perPage, $total)];
}

function cb_admin_uzivatel_detail(mysqli $db, int $idUser): ?array
{
    if ($idUser <= 0) { return null; }
    $stmt = $db->prepare('SELECT u.id_user,p.id_person,p.id_firma,ou.jmeno,ou.prijmeni,u.email,(SELECT t.telefon FROM hr_telefon t WHERE t.id_person=p.id_person AND t.platny=1 AND t.hlavni=1 ORDER BY t.id_telefon DESC LIMIT 1) AS telefon,p.aktivni FROM user u INNER JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(ou2.id_osobni_udaje) FROM hr_osobni_udaje ou2 WHERE ou2.id_person=p.id_person AND ou2.platny=1) WHERE u.id_user=? LIMIT 1');
    $stmt->bind_param('i', $idUser); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!is_array($user)) { return null; }
    return $user;
}
