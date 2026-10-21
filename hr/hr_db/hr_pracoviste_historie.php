<?php
declare(strict_types=1);

/*
 * Ucel souboru: Nacita oddelene historie povolenych pobocek a MAIN a uklada pouze skutecne rozdily.
 * Nezmenena prirazeni zachovavaji puvodni platnost.
 * Cteni i zmeny chrani jednotny osobni rozsah HR.
 * Personal manager muze ukoncit MAIN bez vytvoreni nahradni pobocky; historie zustava zachovana.
 */

require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';
require_once __DIR__ . '/hr_zamestnanci_pristup.php';

/** @return array{pobocky:array<int,array{id_pob:int,nazev:string}>,historie:array<int,array<string,mixed>>} */
function hr_pracoviste_historie(mysqli $db, int $idPerson, int $idUser): array
{
    hr_zamestnanci_vyzaduj_osobu($db, $idUser, $idPerson);
    $stmt = $db->prepare('SELECT id_firma FROM hr_person WHERE id_person = ? LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $idFirma = (int)($stmt->get_result()->fetch_assoc()['id_firma'] ?? 0);
    $stmt->close();

    $stmt = $db->prepare('SELECT id_pob, nazev FROM pobocka WHERE id_firma = ? AND aktivni = 1 ORDER BY id_pob');
    $stmt->bind_param('i', $idFirma);
    $stmt->execute();
    $pobocky = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pobocky[] = ['id_pob' => (int)$row['id_pob'], 'nazev' => (string)$row['nazev']];
    }
    $stmt->close();

    $stmt = $db->prepare('SELECT hp.id_pracoviste, hp.id_pob, p.nazev, hp.hlavni, hp.platnost_od, hp.platnost_do, hp.platny, hp.zruseno FROM hr_pracoviste hp JOIN pobocka p ON p.id_pob = hp.id_pob WHERE hp.id_person = ? ORDER BY COALESCE(hp.platnost_od, \'1000-01-01\') DESC, hp.hlavni DESC, hp.id_pracoviste DESC');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $historie = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id_pracoviste'] = (int)$row['id_pracoviste'];
        $row['id_pob'] = (int)$row['id_pob'];
        $row['hlavni'] = (int)$row['hlavni'];
        $row['platny'] = (int)$row['platny'];
        $historie[] = $row;
    }
    $stmt->close();
    // MAIN ma vlastni intervaly, i kdyz se povolene pobocky vubec nezmenily.
    $stmt = $db->prepare('SELECT m.*, p.nazev FROM ' . cb_hr_main_sql($db, true) . ' m JOIN pobocka p ON p.id_pob=m.id_pob WHERE m.id_person=? ORDER BY COALESCE(m.platnost_od, \'1000-01-01\') DESC,m.id_pracoviste DESC');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $mainHistorie = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return ['pobocky' => $pobocky, 'historie' => $historie, 'main_historie' => $mainHistorie, 'main_pripraveno' => cb_hr_main_pripraveno($db)];
}

// Porovna jen clenstvi k datu zmeny; priznak MAIN nema na povolene pobocky vliv.
function hr_pracoviste_rozdily(array $rows, array $idPobocky, int $idPobHlavni, string $platiOd): array
{
    $pozadovane = [];
    foreach ($idPobocky as $idPob) {
        $pozadovane[$idPob] = 0;
    }
    $ukoncit = [];
    $zrusit = [];
    $aktualni = [];
    $budouciZmena = false;
    foreach ($rows as $row) {
        if ((int)$row['platny'] !== 1 || !empty($row['zruseno'])) continue;
        $od = (string)($row['platnost_od'] ?? '');
        $do = (string)($row['platnost_do'] ?? '');
        if ($do !== '' && $do < $platiOd) continue;
        if ($od > $platiOd) {
            $budouciZmena = true;
            continue;
        }
        if ($do !== '') $budouciZmena = true;
        $idPob = (int)$row['id_pob'];
        if (isset($aktualni[$idPob])) {
            throw new CbUserVisibleException('Pobočka ID ' . $idPob . ' má k datu změny více platných přiřazení. Nejprve opravte jejich překryv.');
        }
        $aktualni[$idPob] = true;
        if (isset($pozadovane[$idPob])) {
            unset($pozadovane[$idPob]);
            continue;
        }
        if ($od === $platiOd) $zrusit[] = (int)$row['id_pracoviste'];
        else $ukoncit[] = (int)$row['id_pracoviste'];
    }
    // Budouci plan nesmime pri zpetne zmene potichu prepsat ani vytvorit prekryv hlavni pobocky.
    if ($budouciZmena && ($ukoncit !== [] || $zrusit !== [] || $pozadovane !== [])) {
        throw new CbUserVisibleException('Po zadaném datu je již naplánovaná změna poboček. Zvolte datum po poslední plánované změně nebo nejprve upravte plán.');
    }
    return ['ukoncit' => $ukoncit, 'zrusit' => $zrusit, 'pridat' => $pozadovane];
}

/** Ulozi rozdily obou evidenci atomicky a povoleni bez MAIN overi na serveru. @param int[] $idPobocky */
function hr_pracoviste_zmenit(mysqli $db, int $idPerson, array $idPobocky, int $idPobHlavni, string $platiOd, int $idUser, string $mainOd): void
{
    hr_zamestnanci_vyzaduj_osobu($db, $idUser, $idPerson);
    cb_hr_main_vyzaduj($db);
    if ($idPobocky === [] || ($idPobHlavni !== 0 && !in_array($idPobHlavni, $idPobocky, true))) {
        throw new CbUserVisibleException('Vyberte alespoň jednu pobočku a jednu z nich jako hlavní.');
    }
    $stmt = $db->prepare('SELECT id_firma FROM hr_person WHERE id_person = ? LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $idFirma = (int)($stmt->get_result()->fetch_assoc()['id_firma'] ?? 0);
    $stmt->close();
    $marks = implode(',', array_fill(0, count($idPobocky), '?'));
    $types = 'i' . str_repeat('i', count($idPobocky));
    $stmt = $db->prepare('SELECT COUNT(*) AS pocet FROM pobocka WHERE id_firma = ? AND aktivni = 1 AND id_pob IN (' . $marks . ')');
    $refs = [&$types, &$idFirma];
    foreach ($idPobocky as $i => $id) { $refs[] = &$idPobocky[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $pocet = (int)($stmt->get_result()->fetch_assoc()['pocet'] ?? 0);
    $stmt->close();
    if ($pocet !== count($idPobocky)) {
        throw new CbUserVisibleException('Vyberte pouze aktivní pobočky zaměstnancovy firmy.');
    }

    $denPred = (new DateTimeImmutable($platiOd))->modify('-1 day')->format('Y-m-d');
    $db->begin_transaction();
    try {
        // Zamkneme osobu i jeji prirazeni, aby soubezna ulozeni nevytvorila duplicity.
        $stmt = $db->prepare('SELECT id_person FROM hr_person WHERE id_person = ? FOR UPDATE');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $stmt->get_result()->free();
        $stmt->close();
        if ($idPobHlavni === 0 && !cb_hr_main_nepovinne($db, $idPerson, $mainOd)) {
            throw new CbUserVisibleException('U této funkce je hlavní pobočka povinná.');
        }
        $stmt = $db->prepare('SELECT id_pracoviste, id_pob, hlavni, platnost_od, platnost_do, platny, zruseno FROM hr_pracoviste WHERE id_person = ? AND platny = 1 FOR UPDATE');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $rozdily = hr_pracoviste_rozdily($rows, $idPobocky, $idPobHlavni, $platiOd);
        $stmt = $db->prepare('SELECT id_main AS id_pracoviste,id_pob,platnost_od,platnost_do,platny,zruseno FROM hr_pracoviste_main WHERE id_person=? AND platny=1 FOR UPDATE');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $mainRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        // Nula je vyslovne bez MAIN: uzavre dosavadni interval a nevklada fiktivni pobocku.
        $mainRozdily = hr_pracoviste_rozdily($mainRows, $idPobHlavni === 0 ? [] : [$idPobHlavni], 0, $mainOd);
        // Nejprve overime obe cele casove osy; rozdilna data nesmi zpusobit MAIN mimo povolene pobocky.
        $novePobocky = hr_pracoviste_simulace($rows, $rozdily, $platiOd);
        $noveMain = hr_pracoviste_simulace($mainRows, $mainRozdily, $mainOd);
        $bezeZmeny = $mainRozdily['ukoncit'] === [] && $mainRozdily['zrusit'] === [] && $mainRozdily['pridat'] === [];
        if (!$bezeZmeny || $rozdily['ukoncit'] !== [] || $rozdily['zrusit'] !== [] || $rozdily['pridat'] !== []) {
            hr_pracoviste_over_main_povoleni($novePobocky, $noveMain, min($platiOd, $mainOd));
        }
        if ($bezeZmeny && $rozdily['ukoncit'] === [] && $rozdily['zrusit'] === [] && $rozdily['pridat'] === []) {
            $db->commit();
            return;
        }
        $stmt = $db->prepare('UPDATE hr_pracoviste SET platnost_do = ? WHERE id_pracoviste = ? AND id_person = ?');
        foreach ($rozdily['ukoncit'] as $idPracoviste) {
            $stmt->bind_param('sii', $denPred, $idPracoviste, $idPerson);
            $stmt->execute();
        }
        $stmt->close();
        $stmt = $db->prepare('UPDATE hr_pracoviste SET platny = 0, zruseno = NOW() WHERE id_pracoviste = ? AND id_person = ?');
        foreach ($rozdily['zrusit'] as $idPracoviste) {
            $stmt->bind_param('ii', $idPracoviste, $idPerson);
            $stmt->execute();
        }
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO hr_pracoviste (id_person, id_pob, hlavni, platnost_od, id_user_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
        foreach ($rozdily['pridat'] as $idPob => $hlavni) {
            $stmt->bind_param('iiisi', $idPerson, $idPob, $hlavni, $platiOd, $idUser);
            $stmt->execute();
        }
        $stmt->close();
        // MAIN meni pouze samostatnou historii, povolene pobocky zustavaji bez dalsich zapisu.
        $mainDenPred = (new DateTimeImmutable($mainOd))->modify('-1 day')->format('Y-m-d');
        $stmt = $db->prepare('UPDATE hr_pracoviste_main SET platnost_do=? WHERE id_main=? AND id_person=?');
        foreach ($mainRozdily['ukoncit'] as $idMain) {
            $stmt->bind_param('sii', $mainDenPred, $idMain, $idPerson);
            $stmt->execute();
        }
        $stmt->close();
        $stmt = $db->prepare('UPDATE hr_pracoviste_main SET platny=0,zruseno=NOW() WHERE id_main=? AND id_person=?');
        foreach ($mainRozdily['zrusit'] as $idMain) {
            $stmt->bind_param('ii', $idMain, $idPerson);
            $stmt->execute();
        }
        $stmt->close();
        foreach ($mainRozdily['pridat'] as $idMainPob => $ignored) {
            cb_hr_main_vlozit($db, $idPerson, $idMainPob, $mainOd, $idUser);
        }
        // Pracoviste a jedna hlavni pobocka jsou soucasti kontroly kompletnosti.
        hr_update_employee_completeness($db, $idPerson);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

// Simuluje plan pred zapisem, aby se validovala skutecna budouci podoba obou evidenci.
function hr_pracoviste_simulace(array $rows, array $rozdily, string $od): array
{
    $pred = (new DateTimeImmutable($od))->modify('-1 day')->format('Y-m-d');
    foreach ($rows as &$row) {
        if (in_array((int)$row['id_pracoviste'], $rozdily['ukoncit'], true)) $row['platnost_do'] = $pred;
        if (in_array((int)$row['id_pracoviste'], $rozdily['zrusit'], true)) $row['platny'] = 0;
    }
    unset($row);
    foreach ($rozdily['pridat'] as $pob => $ignored) {
        $rows[] = ['id_pob' => $pob, 'platnost_od' => $od, 'platnost_do' => null, 'platny' => 1, 'zruseno' => null];
    }
    return $rows;
}

// MAIN musi byt po cele sve platnosti pokryta povolenim; navazujici intervaly se mohou spojit.
function hr_pracoviste_over_main_povoleni(array $pobocky, array $main, string $odKontroly): void
{
    $platneMain = array_filter($main, static fn($r) => (int)$r['platny'] === 1 && empty($r['zruseno']));
    foreach ($platneMain as $i => $m) {
        $od = max($odKontroly, (string)($m['platnost_od'] ?? '1000-01-01'));
        $do = (string)($m['platnost_do'] ?? '9999-12-31');
        if ($do < $od) continue;
        foreach ($platneMain as $j => $other) {
            if ($i !== $j && max($od, (string)($other['platnost_od'] ?? '1000-01-01')) <= min($do, (string)($other['platnost_do'] ?? '9999-12-31'))) {
                throw new CbUserVisibleException('MAIN má překrývající se intervaly. Nejprve opravte jejich historii.');
            }
        }
        $intervaly = array_filter($pobocky, static fn($p) => (int)$p['id_pob'] === (int)$m['id_pob'] && (int)$p['platny'] === 1 && empty($p['zruseno']));
        usort($intervaly, static fn($a, $b) => strcmp((string)($a['platnost_od'] ?? ''), (string)($b['platnost_od'] ?? '')));
        $pokryto = false;
        foreach ($intervaly as $p) {
            $pOd = (string)($p['platnost_od'] ?? '1000-01-01');
            $pDo = (string)($p['platnost_do'] ?? '9999-12-31');
            if ($pDo < $od) continue;
            if ($pOd > $od) break;
            if ($pDo >= $do) { $pokryto = true; break; }
            $od = (new DateTimeImmutable($pDo))->modify('+1 day')->format('Y-m-d');
        }
        if (!$pokryto) {
            throw new CbUserVisibleException('MAIN pobočka ID ' . (int)$m['id_pob'] . ' není po celou dobu povolená. Slaďte datum změny poboček a MAIN.');
        }
    }
}
