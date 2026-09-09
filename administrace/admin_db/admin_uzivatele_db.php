<?php
declare(strict_types=1);

/* Datové operace pro správu lokálních uživatelů Administrace. */

function cb_admin_uzivatele_ciselniky(mysqli $db): array
{
    $out = ['firmy' => [], 'role' => [], 'pobocky' => []];
    $queries = [
        'firmy' => 'SELECT id_firma AS id, obchodni_jmeno AS nazev FROM firma WHERE aktivni=1 ORDER BY obchodni_jmeno',
        'role' => 'SELECT id_role AS id, role AS nazev FROM cis_role WHERE aktivni=1 ORDER BY role',
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

function cb_admin_uzivatele_vytvor(mysqli $db, array $post): int
{
    $idFirma = (int)($post['id_firma'] ?? 0);
    $jmeno = trim((string)($post['jmeno'] ?? ''));
    $prijmeni = trim((string)($post['prijmeni'] ?? ''));
    $email = trim((string)($post['email'] ?? ''));
    $telefon = trim((string)($post['telefon'] ?? ''));
    $idRole = (int)($post['id_role'] ?? 0);
    $pobocky = array_values(array_unique(array_filter(array_map('intval', (array)($post['id_pob'] ?? [])), static fn (int $id): bool => $id >= 0)));
    $idPobHlavni = (int)($post['id_pob_hlavni'] ?? -1);
    $pobAll = (int)($post['pob_all'] ?? 0) === 1;

    if ($idFirma <= 0 || $jmeno === '' || $prijmeni === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Vyplňte firmu, jméno, příjmení a platný e-mail.');
    }
    if ($idRole <= 0 || $pobocky === []) {
        throw new RuntimeException('Vyberte roli a alespoň jednu pobočku.');
    }
    if ($idPobHlavni >= 0 && !in_array($idPobHlavni, $pobocky, true)) {
        throw new RuntimeException('Hlavní pobočka musí být mezi vybranými pobočkami.');
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT 1 FROM firma WHERE id_firma=? AND aktivni=1 LIMIT 1');
        $stmt->bind_param('i', $idFirma); $stmt->execute(); $okFirma = $stmt->get_result()->fetch_row() !== null; $stmt->close();
        if (!$okFirma) { throw new RuntimeException('Vybraná firma není aktivní.'); }

        $stmt = $db->prepare('SELECT 1 FROM cis_role WHERE aktivni=1 AND id_role=? LIMIT 1');
        $stmt->bind_param('i', $idRole); $stmt->execute(); $validRole = $stmt->get_result()->fetch_row() !== null; $stmt->close();
        if (!$validRole) { throw new RuntimeException('Vybraná role není aktivní.'); }

        $marks = implode(',', array_fill(0, count($pobocky), '?'));
        $types = 'i' . str_repeat('i', count($pobocky));
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM pobocka WHERE aktivni=1 AND id_firma=? AND id_pob IN (' . $marks . ')');
        $bind = [&$types, &$idFirma]; foreach ($pobocky as $i => $value) { $bind[] = &$pobocky[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind); $stmt->execute(); $validPob = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); $stmt->close();
        if ($validPob !== count($pobocky)) { throw new RuntimeException('Vybrané pobočky musí být aktivní a patřit vybrané firmě.'); }

        $aktivni = 1; $schvalen = 1; $inSystem = 0; $zdroj = 2; $hash = null;
        $stmt = $db->prepare('INSERT INTO user (id_firma,jmeno,prijmeni,email,heslo_hash,telefon,aktivni,in_system,schvalen,zdroj) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('isssssiiii', $idFirma, $jmeno, $prijmeni, $email, $hash, $telefon, $aktivni, $inSystem, $schvalen, $zdroj);
        $stmt->execute(); $idUser = (int)$db->insert_id; $stmt->close();

        $stmt = $db->prepare('INSERT INTO user_role (id_user,id_role) VALUES (?,?)');
        $stmt->bind_param('ii', $idUser, $idRole); $stmt->execute(); $stmt->close();

        $stmt = $db->prepare('INSERT INTO user_pobocka (id_user,id_pob,main,pob_all) VALUES (?,?,?,?)');
        $saved = $pobAll ? [$idPobHlavni >= 0 ? $idPobHlavni : reset($pobocky)] : $pobocky;
        foreach ($saved as $idPob) { $main = $idPobHlavni >= 0 && $idPob === $idPobHlavni ? 1 : 0; $all = $pobAll ? 1 : 0; $stmt->bind_param('iiii', $idUser, $idPob, $main, $all); $stmt->execute(); }
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }

    cb_user_spojeni_odeslat($db, $idUser);
    return $idUser;
}

function cb_admin_uzivatele_filtry(array $source): array
{
    $raw = is_array($source['usr_f'] ?? null) ? $source['usr_f'] : [];
    $text = static fn (string $key): string => mb_substr(trim((string)($raw[$key] ?? '')), 0, 120, 'UTF-8');
    return [
        'id' => $text('id'), 'uzivatel' => $text('uzivatel'), 'kontakt' => $text('kontakt'),
        'firma' => (string)(int)($raw['firma'] ?? 0), 'role' => (string)(int)($raw['role'] ?? 0),
        'pobocka' => (string)(int)($raw['pobocka'] ?? -1),
        'zdroj' => in_array((string)($raw['zdroj'] ?? 'vse'), ['1', '2', 'vse'], true) ? (string)($raw['zdroj'] ?? 'vse') : 'vse',
        'stav' => in_array((string)($raw['stav'] ?? 'vse'), ['aktivni', 'ceka', 'neaktivni', 'vse'], true) ? (string)($raw['stav'] ?? 'vse') : 'vse',
    ];
}

function cb_admin_uzivatele_nacti(mysqli $db, array $source): array
{
    $filters = cb_admin_uzivatele_filtry($source);
    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($source['usr_per'] ?? 50);
    if (!in_array($perPage, $perOptions, true)) { $perPage = 50; }
    $page = max(1, (int)($source['usr_p'] ?? 1));
    $sortMap = ['id' => 'u.id_user', 'uzivatel' => 'u.prijmeni,u.jmeno', 'kontakt' => 'u.email,u.telefon', 'firma' => 'f.obchodni_jmeno', 'role' => 'role', 'pobocky' => 'pobocky', 'zdroj' => 'u.zdroj', 'stav' => 'u.aktivni'];
    $sort = (string)($source['usr_sort'] ?? 'uzivatel');
    if (!isset($sortMap[$sort])) { $sort = 'uzivatel'; }
    $dir = strtolower((string)($source['usr_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    $where = [];
    $params = [];
    $addLike = static function (string $field, string $value) use (&$where, &$params): void { if ($value !== '') { $where[] = $field . ' LIKE ?'; $params[] = '%' . $value . '%'; } };
    $addLike('CAST(u.id_user AS CHAR)', $filters['id']);
    $addLike('CONCAT_WS(" ",u.jmeno,u.prijmeni)', $filters['uzivatel']);
    $addLike('CONCAT_WS(" ",u.email,u.telefon)', $filters['kontakt']);
    if ((int)$filters['firma'] > 0) { $where[] = 'u.id_firma=?'; $params[] = (int)$filters['firma']; }
    if ((int)$filters['role'] > 0) { $where[] = 'EXISTS (SELECT 1 FROM user_role urf WHERE urf.id_user=u.id_user AND urf.id_role=?)'; $params[] = (int)$filters['role']; }
    if ((int)$filters['pobocka'] >= 0) { $where[] = 'EXISTS (SELECT 1 FROM user_pobocka upf WHERE upf.id_user=u.id_user AND (upf.id_pob=? OR upf.pob_all=1))'; $params[] = (int)$filters['pobocka']; }
    if ($filters['zdroj'] !== 'vse') { $where[] = 'u.zdroj=?'; $params[] = (int)$filters['zdroj']; }
    if ($filters['stav'] === 'aktivni') { $where[] = 'u.aktivni=1 AND u.heslo_hash IS NOT NULL AND u.heslo_hash<>""'; }
    if ($filters['stav'] === 'ceka') { $where[] = 'u.aktivni=1 AND (u.heslo_hash IS NULL OR u.heslo_hash="")'; }
    if ($filters['stav'] === 'neaktivni') { $where[] = 'u.aktivni=0'; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $db->prepare('SELECT COUNT(*) AS c FROM user u' . $whereSql);
    if ($params !== []) { $types = implode('', array_map(static fn ($value): string => is_int($value) ? 'i' : 's', $params)); $bind = [&$types]; foreach ($params as $index => $value) { $bind[] = &$params[$index]; } call_user_func_array([$count, 'bind_param'], $bind); }
    $count->execute(); $total = (int)($count->get_result()->fetch_assoc()['c'] ?? 0); $count->close();
    $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare('
        SELECT u.id_user,u.jmeno,u.prijmeni,u.email,u.telefon,u.aktivni,u.zdroj,f.obchodni_jmeno,
               GROUP_CONCAT(DISTINCT r.role ORDER BY r.role SEPARATOR ", ") AS role,
               GROUP_CONCAT(DISTINCT p.nazev ORDER BY p.nazev SEPARATOR ", ") AS pobocky,
               MAX(up.pob_all) AS pob_all, MAX(up.main) AS ma_main,
               MAX(CASE WHEN u.heslo_hash IS NULL OR u.heslo_hash="" THEN 0 ELSE 1 END) AS ma_heslo
        FROM user u
        LEFT JOIN firma f ON f.id_firma=u.id_firma
        LEFT JOIN user_role ur ON ur.id_user=u.id_user
        LEFT JOIN cis_role r ON r.id_role=ur.id_role
        LEFT JOIN user_pobocka up ON up.id_user=u.id_user
        LEFT JOIN pobocka p ON p.id_pob=up.id_pob
        ' . $whereSql . '
        GROUP BY u.id_user,u.jmeno,u.prijmeni,u.email,u.telefon,u.aktivni,u.zdroj,f.obchodni_jmeno
        ORDER BY ' . $sortMap[$sort] . ' ' . strtoupper($dir) . ', u.id_user DESC LIMIT ? OFFSET ?
    ');
    $bindParams = $params; $bindParams[] = $perPage; $bindParams[] = $offset;
    $types = implode('', array_map(static fn ($value): string => is_int($value) ? 'i' : 's', $bindParams)); $bind = [&$types]; foreach ($bindParams as $index => $value) { $bind[] = &$bindParams[$index]; } call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute(); $res=$stmt->get_result(); $rows=[];
    while ($row=$res->fetch_assoc()) { $rows[]=$row; } $stmt->close();
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage,'per_options'=>$perOptions,'filters'=>$filters,'sort'=>$sort,'dir'=>$dir,'first_row'=>$total === 0 ? 0 : $offset + 1,'last_row'=>min($offset + $perPage, $total)];
}

function cb_admin_uzivatel_detail(mysqli $db, int $idUser): ?array
{
    if ($idUser <= 0) { return null; }
    $stmt = $db->prepare('SELECT id_user,id_firma,jmeno,prijmeni,email,telefon,aktivni,schvalen,zdroj,heslo_hash FROM user WHERE id_user=? LIMIT 1');
    $stmt->bind_param('i', $idUser); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!is_array($user)) { return null; }
    $stmt=$db->prepare('SELECT id_role FROM user_role WHERE id_user=? ORDER BY id_role'); $stmt->bind_param('i',$idUser); $stmt->execute(); $res=$stmt->get_result(); $roles=[]; while($row=$res->fetch_assoc()){$roles[]=(int)$row['id_role'];}$stmt->close();
    $stmt=$db->prepare('SELECT id_pob,main,pob_all FROM user_pobocka WHERE id_user=? ORDER BY id_pob'); $stmt->bind_param('i',$idUser); $stmt->execute(); $res=$stmt->get_result(); $pob=[]; $pobAll=false; $main=-1; while($row=$res->fetch_assoc()){ $pob[]=(int)$row['id_pob']; $pobAll=$pobAll || (int)$row['pob_all']===1; if((int)$row['main']===1){$main=(int)$row['id_pob'];}} $stmt->close();
    $user['role']=$roles; $user['pobocky']=$pob; $user['pob_all']=$pobAll; $user['id_pob_hlavni']=$main; return $user;
}

function cb_admin_uzivatel_uloz(mysqli $db, array $post): array
{
    $idUser = (int)($post['id_user'] ?? 0);
    $before = cb_admin_uzivatel_detail($db, $idUser);
    if (!is_array($before)) { throw new RuntimeException('Uživatel neexistuje.'); }
    $idFirma = (int)($post['id_firma'] ?? 0); $idRole = (int)($post['id_role'] ?? 0);
    $jmeno = trim((string)($post['jmeno'] ?? '')); $prijmeni = trim((string)($post['prijmeni'] ?? ''));
    $email = trim((string)($post['email'] ?? '')); $telefon = trim((string)($post['telefon'] ?? ''));
    $aktivni = (int)($post['aktivni'] ?? 0) === 1 ? 1 : 0;
    $pobocky = array_values(array_unique(array_filter(array_map('intval', (array)($post['id_pob'] ?? [])), static fn (int $id): bool => $id >= 0)));
    $idPobHlavni = (int)($post['id_pob_hlavni'] ?? -1); $pobAll = (int)($post['pob_all'] ?? 0) === 1;
    if ($idFirma <= 0 || $idRole <= 0 || $jmeno === '' || $prijmeni === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $pobocky === []) { throw new RuntimeException('Vyplňte firmu, jméno, příjmení, platný e-mail, roli a pobočku.'); }
    if ($idPobHlavni >= 0 && !in_array($idPobHlavni, $pobocky, true)) { throw new RuntimeException('Hlavní pobočka musí být mezi vybranými pobočkami.'); }
    $db->begin_transaction();
    try {
        $stmt=$db->prepare('SELECT 1 FROM firma WHERE id_firma=? AND aktivni=1 LIMIT 1'); $stmt->bind_param('i',$idFirma); $stmt->execute(); $okFirma=$stmt->get_result()->fetch_row()!==null; $stmt->close();
        $stmt=$db->prepare('SELECT 1 FROM cis_role WHERE id_role=? AND aktivni=1 LIMIT 1'); $stmt->bind_param('i',$idRole); $stmt->execute(); $okRole=$stmt->get_result()->fetch_row()!==null; $stmt->close();
        if (!$okFirma || !$okRole) { throw new RuntimeException('Firma nebo role není aktivní.'); }
        $marks=implode(',', array_fill(0,count($pobocky),'?')); $types='i'.str_repeat('i',count($pobocky)); $stmt=$db->prepare('SELECT COUNT(*) AS c FROM pobocka WHERE aktivni=1 AND id_firma=? AND id_pob IN ('.$marks.')'); $bind=[&$types,&$idFirma]; foreach($pobocky as $index=>$value){$bind[]=&$pobocky[$index];} call_user_func_array([$stmt,'bind_param'],$bind); $stmt->execute(); $validPob=(int)($stmt->get_result()->fetch_assoc()['c']??0);$stmt->close();
        if($validPob!==count($pobocky)){throw new RuntimeException('Vybrané pobočky musí patřit vybrané firmě a být aktivní.');}
        $stmt=$db->prepare('UPDATE user SET id_firma=?,jmeno=?,prijmeni=?,email=?,telefon=?,aktivni=? WHERE id_user=?'); $stmt->bind_param('issssii',$idFirma,$jmeno,$prijmeni,$email,$telefon,$aktivni,$idUser);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('DELETE FROM user_role WHERE id_user=?');$stmt->bind_param('i',$idUser);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('INSERT INTO user_role (id_user,id_role) VALUES (?,?)');$stmt->bind_param('ii',$idUser,$idRole);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('DELETE FROM user_pobocka WHERE id_user=?');$stmt->bind_param('i',$idUser);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('INSERT INTO user_pobocka (id_user,id_pob,main,pob_all) VALUES (?,?,?,?)'); $saved=$pobAll?[$idPobHlavni>=0?$idPobHlavni:reset($pobocky)]:$pobocky; foreach($saved as $idPob){$main=$idPobHlavni>=0&&$idPob===$idPobHlavni?1:0;$all=$pobAll?1:0;$stmt->bind_param('iiii',$idUser,$idPob,$main,$all);$stmt->execute();}$stmt->close();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
    return ['id_user'=>$idUser,'before'=>$before,'after'=>cb_admin_uzivatel_detail($db,$idUser)];
}
