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

    return $rows;
}

/**
 * Prevede jednu nebo vice povolenych oblasti na masku jejich aktivnich pobocek.
 */
function hr_vd_pobocky_mask_z_oblasti(mysqli $db, mixed $rawOblasti): int
{
    if (!is_array($rawOblasti)) {
        throw new RuntimeException('Vyberte alespoň jednu oblast pracoviště.');
    }

    $vybraneOblasti = [];
    foreach ($rawOblasti as $rawOblast) {
        $oblast = trim((string)$rawOblast);
        if ($oblast !== '') {
            $vybraneOblasti[$oblast] = true;
        }
    }
    if ($vybraneOblasti === []) {
        throw new RuntimeException('Vyberte alespoň jednu oblast pracoviště.');
    }

    $pobockyPodleOblasti = [];
    $result = $db->query("SELECT id_pob, oblast FROM pobocka WHERE aktivni = 1 AND id_pob > 0 AND oblast <> '' ORDER BY id_pob");
    while ($row = $result->fetch_assoc()) {
        $idPob = (int)$row['id_pob'];
        $oblast = trim((string)$row['oblast']);
        if ($idPob > 62) {
            $result->free();
            throw new RuntimeException('Pobočku nelze uložit do současného formátu pracovních podmínek.');
        }
        $pobockyPodleOblasti[$oblast][] = $idPob;
    }
    $result->free();

    $pobockyMask = 0;
    foreach (array_keys($vybraneOblasti) as $oblast) {
        if (!isset($pobockyPodleOblasti[$oblast])) {
            throw new RuntimeException('Vybraná oblast pracoviště není platná.');
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
        throw new RuntimeException('Chybí povinné údaje pro uložení akce.');
    }
    if ($idUserZadal <= 0) {
        throw new RuntimeException('Chybí přihlášený uživatel.');
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
                throw new RuntimeException('Vyplňte pracovní vztah, oblast, pozici, datum nástupu a mzdu.');
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
