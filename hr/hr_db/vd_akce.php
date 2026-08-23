<?php
declare(strict_types=1);

/**
 * DB operace pro historii a ukladani akci u verejneho dotazniku.
 */

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
            ou.jmeno AS zadal_jmeno,
            ou.prijmeni AS zadal_prijmeni
        FROM hr_vd_akce a
        INNER JOIN hr_cis_vd_akce_vysledek v
            ON v.id_vd_akce_vysledek = a.id_vd_akce_vysledek
        INNER JOIN hr_cis_vd_akce_typ t
            ON t.id_vd_akce_typ = v.id_vd_akce_typ
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = a.id_person_zadal
           AND ou.platny = 1
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

    return $rows;
}

function hr_uloz_vd_akci(mysqli $db, int $idVd, int $idVdAkceVysledek, string $terminDate, string $terminTime, string $poznamka, int $idPersonZadal, array $podminky): void
{
    if ($idVd <= 0 || $idVdAkceVysledek <= 0) {
        throw new RuntimeException('Chybí povinné údaje pro uložení akce.');
    }
    if ($idPersonZadal <= 0) {
        throw new RuntimeException('Chybí HR osoba přihlášeného uživatele.');
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
            throw new RuntimeException('Zvolený výsledek akce není povolený.');
        }

        $vyzadujeDate = (int)$vysledek['vyzaduje_termin_date'] === 1;
        $vyzadujeTime = (int)$vysledek['vyzaduje_termin_time'] === 1;
        if ($terminDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $terminDate) !== 1) {
            throw new RuntimeException('Datum termínu nemá platný formát.');
        }
        if ($terminTime !== '') {
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $terminTime, $cas) !== 1 || (int)$cas[1] < 8 || (int)$cas[1] > 20 || !in_array((int)$cas[2], [0, 15, 30, 45], true)) {
                throw new RuntimeException('Čas termínu musí být od 8:00 do 20:45 po 15 minutách.');
            }
            $terminTime = str_pad((string)(int)$cas[1], 2, '0', STR_PAD_LEFT) . ':' . $cas[2];
        }
        if ($vyzadujeDate && $terminDate === '') {
            throw new RuntimeException('Vyplňte datum dalšího termínu.');
        }
        if ($vyzadujeTime && $terminTime === '') {
            throw new RuntimeException('Vyplňte čas dalšího termínu.');
        }
        if (!$vyzadujeDate && ($terminDate !== '' || $terminTime !== '')) {
            throw new RuntimeException('Pro zvolený výsledek se termín nezadává.');
        }
        if ($terminTime !== '' && $terminDate === '') {
            throw new RuntimeException('Čas termínu lze uložit pouze s datem.');
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
            throw new RuntimeException('Veřejný dotazník nebyl nalezen.');
        }
        if ($vysledek['id_vychozi_vd_stav'] !== null && (int)$vysledek['id_vychozi_vd_stav'] !== (int)$vd['id_vd_stav']) {
            throw new RuntimeException('Zvolená akce není v aktuálním stavu VD povolená.');
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
            $idPob = (int)($podminky['id_pob'] ?? 0);
            $idSlot = (int)($podminky['id_slot'] ?? 0);
            $datumNastupu = trim((string)($podminky['datum_nastupu'] ?? ''));
            $mzda = trim((string)($podminky['mzda'] ?? ''));
            $fixni = (int)($podminky['mzda_fixni'] ?? 0) === 1;
            if ($idVztah <= 0 || $idPob <= 0 || $idSlot <= 0 || $datumNastupu === '' || strtotime($datumNastupu) === false || $mzda === '' || preg_match('/^\d+$/', $mzda) !== 1 || strlen($mzda) > 10 || (strlen($mzda) === 10 && $mzda > '2147483647')) {
                throw new RuntimeException('Vyplňte pracovní vztah, pobočku, pozici, datum nástupu a mzdu.');
            }

            $pobockyMask = 1 << $idPob;
            $slotyMask = 1 << $idSlot;
            $mzdaFixni = $fixni ? $mzda : null;
            $mzdaHodinova = $fixni ? null : $mzda;
            $stmt = $db->prepare('
                INSERT INTO hr_vd_podminky
                    (id_vd, pobocky_mask, sloty_mask, id_pracovni_vztah_typ, datum_nastupu, mzda_mesicni_fix, mzda_hodinova, id_person_zadal, vytvoreno, platny)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
            ');
            $stmt->bind_param('iiiisssi', $idVd, $pobockyMask, $slotyMask, $idVztah, $datumNastupu, $mzdaFixni, $mzdaHodinova, $idPersonZadal);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare("\n            INSERT INTO hr_vd_akce\n                (id_vd, id_vd_akce_vysledek, id_person_zadal, termin_date, termin_time, poznamka)\n            VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)\n        ");
        $stmt->bind_param('iiisss', $idVd, $idVdAkceVysledek, $idPersonZadal, $terminDate, $terminTime, $poznamkaDb);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
