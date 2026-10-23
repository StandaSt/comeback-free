<?php
declare(strict_types=1);

/*
 * Ucel: Cte archiv puvodnich povolenych pobocek a historii mnoziny MAIN.
 * Cisty rozdilovy vypocet sdili novy zapis mist vykonu prace, aby nezmenene MAIN zachovaly ucinnost.
 * Cteni i zmeny chrani jednotny osobni rozsah HR.
 * Aktualni povoleni se zde jiz nezapisuji; novou evidenci spravuje hr_mista_zapis.php.
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
