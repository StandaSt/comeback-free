<?php
declare(strict_types=1);

/**
 * DB operace pro historii a ukladani akci u verejneho dotazniku.
 */

/**
 * Zapise kazde otevreni detailu VD do jeho domenove historie.
 */
function hr_zapis_vd_otevreni(mysqli $db, int $idVd, int $idUser): bool
{
    if ($idVd <= 0 || $idUser <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare('
            SELECT v.id_vd_akce_vysledek
            FROM hr_cis_vd_akce_vysledek v
            INNER JOIN hr_cis_vd_akce_typ t
                ON t.id_vd_akce_typ = v.id_vd_akce_typ
               AND t.aktivni = 1
            WHERE t.id_vd_akce_typ = 2
            ORDER BY v.id_vd_akce_vysledek ASC
            LIMIT 1
        ');
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $idVysledek = is_array($row) ? (int)($row['id_vd_akce_vysledek'] ?? 0) : 0;
        if ($idVysledek <= 0) {
            cb_chyba_oznam(
                new RuntimeException('Pro akci Otevření VD chybí výsledek v číselníku.'),
                ['module' => 'HR', 'action' => 'Zápis otevření VD', 'table' => 'hr_cis_vd_akce_vysledek']
            );
            return false;
        }

        $stmt = $db->prepare('
            INSERT INTO hr_vd_akce
                (id_vd, id_vd_akce_vysledek, id_user_zadal)
            SELECT vd.id_vd, ?, ?
            FROM hr_vd vd
            WHERE vd.id_vd = ?
              AND vd.aktivni = 1
        ');
        $stmt->bind_param('iii', $idVysledek, $idUser, $idVd);
        $stmt->execute();
        $ulozeno = $stmt->affected_rows === 1;
        $stmt->close();

        return $ulozeno;
    } catch (Throwable $e) {
        cb_chyba_oznam($e, ['module' => 'HR', 'action' => 'Zápis otevření VD', 'table' => 'hr_vd_akce']);
        return false;
    }
}

function hr_nacti_vd_akce(mysqli $db, int $idVd): array
{
    if ($idVd <= 0) {
        return [];
    }

    $stmt = $db->prepare('
        SELECT
            a.id_vd_akce,
            a.akce_kdy,
            a.termin_date,
            a.termin_time,
            a.poznamka,
            v.vysledek,
            t.nazev AS akce_typ_nazev,
            u.jmeno AS zadal_jmeno,
            u.prijmeni AS zadal_prijmeni
        FROM hr_vd_akce a
        INNER JOIN hr_cis_vd_akce_vysledek v
            ON v.id_vd_akce_vysledek = a.id_vd_akce_vysledek
        INNER JOIN hr_cis_vd_akce_typ t
            ON t.id_vd_akce_typ = v.id_vd_akce_typ
        LEFT JOIN user u
            ON u.id_user = a.id_user_zadal
        WHERE a.id_vd = ?
        ORDER BY a.akce_kdy DESC, a.id_vd_akce DESC
    ');
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $zadal = trim((string)($row['zadal_prijmeni'] ?? '') . ' ' . (string)($row['zadal_jmeno'] ?? ''));
        $rows[] = $row + [
            'zadal_label' => $zadal !== '' ? $zadal : '-',
            'poznamka' => trim((string)($row['poznamka'] ?? '')) !== '' ? (string)$row['poznamka'] : '-',
        ];
    }
    $stmt->close();

    $stmt = $db->prepare('
        SELECT odeslano
        FROM hr_vd
        WHERE id_vd = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $vd = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (is_array($vd) && trim((string)($vd['odeslano'] ?? '')) !== '') {
        $rows[] = [
            'id_vd_akce' => 0,
            'akce_kdy' => (string)$vd['odeslano'],
            'termin_date' => null,
            'termin_time' => null,
            'poznamka' => '-',
            'vysledek' => 'Přijat do IS',
            'akce_typ_nazev' => 'Veřejný dotazník',
            'zadal_label' => 'Systém',
        ];
    }

    $stmt = $db->prepare('
        SELECT pouzito
        FROM hr_vd_token
        WHERE id_vd = ?
          AND pouzito IS NOT NULL
        ORDER BY pouzito ASC, id_vd_token ASC
    ');
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id_vd_akce' => 0,
            'akce_kdy' => (string)$row['pouzito'],
            'termin_date' => null,
            'termin_time' => null,
            'poznamka' => '-',
            'vysledek' => 'Potvrzeno uchazečem',
            'akce_typ_nazev' => 'Potvrzení e-mailu',
            'zadal_label' => 'Systém',
        ];
    }
    $stmt->close();

    $stmt = $db->prepare('
        SELECT id_nd, odeslano, vyplneno
        FROM hr_nd
        WHERE id_vd = ?
          AND aktivni = 1
        ORDER BY id_nd ASC
    ');
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (trim((string)($row['odeslano'] ?? '')) !== '') {
            $rows[] = [
                'id_vd_akce' => 0,
                'akce_kdy' => (string)$row['odeslano'],
                'termin_date' => null,
                'termin_time' => null,
                'poznamka' => '-',
                'vysledek' => 'Odeslán uchazeči',
                'akce_typ_nazev' => 'Nástupní dotazník',
                'zadal_label' => 'Systém',
            ];
        }
        if (trim((string)($row['vyplneno'] ?? '')) !== '') {
            $rows[] = [
                'id_vd_akce' => 0,
                'akce_kdy' => (string)$row['vyplneno'],
                'termin_date' => null,
                'termin_time' => null,
                'poznamka' => '-',
                'vysledek' => 'Přijat do IS',
                'akce_typ_nazev' => 'Nástupní dotazník',
                'zadal_label' => 'Systém',
            ];
        }
    }
    $stmt->close();

    $stmt = $db->prepare('
        SELECT
            d.id_dokument,
            d.nazev,
            dt.nazev AS typ_nazev,
            d.vytvoreno,
            d.podpis_odeslano,
            d.podpis_podepsano,
            u.jmeno,
            u.prijmeni
        FROM hr_dokument d
        INNER JOIN hr_cis_dokument_typ dt
            ON dt.id_dokument_typ = d.id_dokument_typ
        LEFT JOIN user u
            ON u.id_user = d.id_user_zadal
        WHERE d.id_vd = ?
          AND d.platny = 1
        ORDER BY d.vytvoreno ASC, d.id_dokument ASC
    ');
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $zadal = trim((string)($row['prijmeni'] ?? '') . ' ' . (string)($row['jmeno'] ?? ''));
        $nazev = trim((string)($row['nazev'] ?? ''));
        $typ = trim((string)($row['typ_nazev'] ?? ''));
        $detailDokumentu = $nazev !== '' ? $nazev : ($typ !== '' ? $typ : 'Dokument');
        if (trim((string)($row['vytvoreno'] ?? '')) !== '') {
            $rows[] = [
                'id_vd_akce' => 0,
                'akce_kdy' => (string)$row['vytvoreno'],
                'termin_date' => null,
                'termin_time' => null,
                'poznamka' => $detailDokumentu,
                'vysledek' => 'Vytvořen',
                'akce_typ_nazev' => $typ !== '' ? $typ : 'Dokument',
                'zadal_label' => $zadal !== '' ? $zadal : 'Systém',
            ];
        }
        if (trim((string)($row['podpis_odeslano'] ?? '')) !== '') {
            $rows[] = [
                'id_vd_akce' => 0,
                'akce_kdy' => (string)$row['podpis_odeslano'],
                'termin_date' => null,
                'termin_time' => null,
                'poznamka' => $detailDokumentu,
                'vysledek' => 'Odeslán k podpisu',
                'akce_typ_nazev' => 'Podpis dokumentu',
                'zadal_label' => 'Systém',
            ];
        }
        if (trim((string)($row['podpis_podepsano'] ?? '')) !== '') {
            $rows[] = [
                'id_vd_akce' => 0,
                'akce_kdy' => (string)$row['podpis_podepsano'],
                'termin_date' => null,
                'termin_time' => null,
                'poznamka' => $detailDokumentu,
                'vysledek' => 'Podepsán',
                'akce_typ_nazev' => 'Podpis dokumentu',
                'zadal_label' => 'Systém',
            ];
        }
    }
    $stmt->close();

    usort($rows, static function (array $a, array $b): int {
        $casA = strtotime((string)($a['akce_kdy'] ?? '')) ?: 0;
        $casB = strtotime((string)($b['akce_kdy'] ?? '')) ?: 0;
        if ($casA !== $casB) {
            return $casB <=> $casA;
        }

        return (int)($b['id_vd_akce'] ?? 0) <=> (int)($a['id_vd_akce'] ?? 0);
    });

    return $rows;
}

/**
 * Prevede jednu nebo vice povolenych oblasti na masku jejich aktivnich pobocek.
 */
function hr_vd_pobocky_mask_z_oblasti(mysqli $db, mixed $rawOblasti): int
{
    if (!is_array($rawOblasti)) {
        throw new CbUserVisibleException('Vyberte alespoň jednu oblast pracoviště.');
    }

    $vybraneOblasti = [];
    foreach ($rawOblasti as $rawOblast) {
        $oblast = trim((string)$rawOblast);
        if ($oblast !== '') {
            $vybraneOblasti[$oblast] = true;
        }
    }
    if ($vybraneOblasti === []) {
        throw new CbUserVisibleException('Vyberte alespoň jednu oblast pracoviště.');
    }

    $pobockyPodleOblasti = [];
    $result = $db->query("SELECT id_pob, oblast FROM pobocka WHERE aktivni = 1 AND id_pob > 0 AND oblast <> '' ORDER BY id_pob");
    while ($row = $result->fetch_assoc()) {
        $idPob = (int)$row['id_pob'];
        $oblast = trim((string)$row['oblast']);
        if ($idPob > 62) {
            $result->free();
            throw new CbUserVisibleException('Vybranou pobočku nelze uložit do současného formátu pracovních podmínek.');
        }
        $pobockyPodleOblasti[$oblast][] = $idPob;
    }
    $result->free();

    $pobockyMask = 0;
    foreach (array_keys($vybraneOblasti) as $oblast) {
        if (!isset($pobockyPodleOblasti[$oblast])) {
            throw new CbUserVisibleException('Vybraná oblast pracoviště není platná.');
        }
        foreach ($pobockyPodleOblasti[$oblast] as $idPob) {
            $pobockyMask |= 1 << $idPob;
        }
    }

    return $pobockyMask;
}

function hr_uloz_vd_akci(mysqli $db, int $idVd, int $idVdAkceVysledek, string $terminDate, string $terminTime, string $poznamka, int $idUserZadal, array $podminky): void
{
    if ($idVd <= 0 || $idVdAkceVysledek <= 0) {
        throw new CbUserVisibleException('Doplňte povinné údaje náborové akce.');
    }
    if ($idUserZadal <= 0) {
        throw new CbUserVisibleException('Přihlášení vypršelo. Přihlaste se prosím znovu.');
    }

    $terminDate = trim($terminDate);
    $terminTime = trim($terminTime);
    $poznamkaDb = trim($poznamka) !== '' ? trim($poznamka) : null;

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('
            SELECT id_vychozi_vd_stav, id_cilovy_vd_stav, vyzaduje_termin_date, vyzaduje_termin_time
            FROM hr_cis_vd_akce_vysledek
            WHERE id_vd_akce_vysledek = ?
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->bind_param('i', $idVdAkceVysledek);
        $stmt->execute();
        $vysledek = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($vysledek)) {
            throw new CbUserVisibleException('Zvolený výsledek akce není povolený.');
        }

        $vyzadujeDate = (int)$vysledek['vyzaduje_termin_date'] === 1;
        $vyzadujeTime = (int)$vysledek['vyzaduje_termin_time'] === 1;
        if ($terminDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $terminDate) !== 1) {
            throw new CbUserVisibleException('Datum termínu nemá platný formát.');
        }
        if ($terminTime !== '') {
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $terminTime, $cas) !== 1 || (int)$cas[1] < 8 || (int)$cas[1] > 20 || !in_array((int)$cas[2], [0, 15, 30, 45], true)) {
                throw new CbUserVisibleException('Čas termínu musí být od 8:00 do 20:45 po 15 minutách.');
            }
            $terminTime = str_pad((string)(int)$cas[1], 2, '0', STR_PAD_LEFT) . ':' . $cas[2];
        }
        if ($vyzadujeDate && $terminDate === '') {
            throw new CbUserVisibleException('Vyplňte datum dalšího termínu.');
        }
        if ($vyzadujeTime && $terminTime === '') {
            throw new CbUserVisibleException('Vyplňte čas dalšího termínu.');
        }
        if (!$vyzadujeDate && ($terminDate !== '' || $terminTime !== '')) {
            throw new CbUserVisibleException('Pro zvolený výsledek se termín nezadává.');
        }
        if ($terminTime !== '' && $terminDate === '') {
            throw new CbUserVisibleException('Čas termínu lze uložit pouze s datem.');
        }

        $stmt = $db->prepare('
            SELECT id_vd, id_vd_stav
            FROM hr_vd
            WHERE id_vd = ? AND aktivni = 1
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->bind_param('i', $idVd);
        $stmt->execute();
        $vd = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($vd)) {
            throw new CbUserVisibleException('Veřejný dotazník nebyl nalezen.');
        }
        if ($vysledek['id_vychozi_vd_stav'] !== null && (int)$vysledek['id_vychozi_vd_stav'] !== (int)$vd['id_vd_stav']) {
            throw new CbUserVisibleException('Zvolená akce není v aktuálním stavu VD povolená.');
        }

        if ($vysledek['id_cilovy_vd_stav'] !== null) {
            $idCilovyStav = (int)$vysledek['id_cilovy_vd_stav'];
            $stmt = $db->prepare('
                UPDATE hr_vd
                SET id_vd_stav = ?, upraveno = NOW()
                WHERE id_vd = ?
            ');
            $stmt->bind_param('ii', $idCilovyStav, $idVd);
            $stmt->execute();
            $stmt->close();
        }

        if ((int)($vysledek['id_cilovy_vd_stav'] ?? 0) === 24) {
            $idVztah = (int)($podminky['id_pracovni_vztah_typ'] ?? 0);
            $idSlot = (int)($podminky['id_slot'] ?? 0);
            $datumNastupu = trim((string)($podminky['datum_nastupu'] ?? ''));
            $mzda = trim((string)($podminky['mzda'] ?? ''));
            $mzdaTyp = trim((string)($podminky['mzda_typ'] ?? ''));
            $datum = DateTimeImmutable::createFromFormat('!Y-m-d', $datumNastupu);
            $datumChyby = DateTimeImmutable::getLastErrors();
            if (
                $idVztah <= 0
                || $idSlot <= 0
                || $idSlot > 62
                || $datum === false
                || ($datumChyby !== false && ($datumChyby['warning_count'] > 0 || $datumChyby['error_count'] > 0))
                || $datum->format('Y-m-d') !== $datumNastupu
                || !in_array($mzdaTyp, ['mesicni', 'hodinova'], true)
                || $mzda === ''
                || preg_match('/^\d+$/', $mzda) !== 1
                || strlen($mzda) > 10
                || (strlen($mzda) === 10 && $mzda > '2147483647')
            ) {
                throw new CbUserVisibleException('Vyplňte pracovní vztah, oblast, pozici, datum nástupu a mzdu.');
            }

            $pobockyMask = hr_vd_pobocky_mask_z_oblasti($db, $podminky['pracoviste_oblasti'] ?? null);
            $slotyMask = 1 << $idSlot;
            $mzdaFixni = $mzdaTyp === 'mesicni' ? $mzda : null;
            $mzdaHodinova = $mzdaTyp === 'hodinova' ? $mzda : null;
            $stmt = $db->prepare('
                INSERT INTO hr_vd_podminky
                    (id_vd, pobocky_mask, sloty_mask, id_pracovni_vztah_typ, datum_nastupu, mzda_mesicni_fix, mzda_hodinova, id_user_zadal, vytvoreno, platny)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ');
            $stmt->bind_param('iiiisssi', $idVd, $pobockyMask, $slotyMask, $idVztah, $datumNastupu, $mzdaFixni, $mzdaHodinova, $idUserZadal);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare("\n            INSERT INTO hr_vd_akce\n                (id_vd, id_vd_akce_vysledek, id_user_zadal, termin_date, termin_time, poznamka)\n            VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)\n        ");
        $stmt->bind_param('iiisss', $idVd, $idVdAkceVysledek, $idUserZadal, $terminDate, $terminTime, $poznamkaDb);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
