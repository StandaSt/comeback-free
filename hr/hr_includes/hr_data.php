<?php
declare(strict_types=1);

function hr_current_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? null;
    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}

function hr_fetch_lookup(mysqli $db, string $table, string $idColumn, string $labelColumn, string $orderColumn = ''): array
{
    $allowed = [
        'hr_pracovni_vztah_typ' => ['id_pracovni_vztah_typ', 'nazev', 'poradi'],
        'pobocka' => ['id_pob', 'nazev', 'id_pob'],
        'cis_slot' => ['id_slot', 'slot', 'id_slot'],
    ];

    if (!isset($allowed[$table])) {
        return [];
    }

    [$safeId, $safeLabel, $safeOrder] = $allowed[$table];
    if ($idColumn !== $safeId || $labelColumn !== $safeLabel) {
        return [];
    }

    $orderBy = $orderColumn !== '' ? $safeOrder : $safeId;
    $where = '';
    if ($table === 'hr_pracovni_vztah_typ') {
        $where = ' WHERE aktivni = 1';
    }

    $rows = [];
    $result = $db->query("SELECT {$safeId} AS id, {$safeLabel} AS label FROM {$table}{$where} ORDER BY {$orderBy}");
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'label' => (string)$row['label'],
        ];
    }

    return $rows;
}

function hr_fetch_dashboard(mysqli $db): array
{
    $counts = [
        'aktivni' => 0,
        'priprava' => 0,
        'preruseny' => 0,
        'ukonceny' => 0,
    ];

    $result = $db->query('SELECT stav, COUNT(*) AS cnt FROM hr_zamestnanec GROUP BY stav');
    while ($row = $result->fetch_assoc()) {
        $stav = (string)$row['stav'];
        if (array_key_exists($stav, $counts)) {
            $counts[$stav] = (int)$row['cnt'];
        }
    }

    return [
        'counts' => $counts,
        'latest' => hr_fetch_employees($db, 5),
    ];
}

function hr_fetch_employees(mysqli $db, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));
    $sql = "
        SELECT
            z.id_zamestnanec,
            z.osobni_cislo,
            z.stav,
            z.zadano,
            ou.jmeno,
            ou.prijmeni,
            pv.datum_nastupu,
            p.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.kod AS vztah_kod
        FROM hr_zamestnanec z
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_zamestnanec = z.id_zamestnanec
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_zamestnanec = z.id_zamestnanec
           AND pv.platny = 1
        LEFT JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_zamestnanec_pracoviste zp
            ON zp.id_zamestnanec = z.id_zamestnanec
           AND zp.platny = 1
           AND zp.hlavni = 1
        LEFT JOIN pobocka p
            ON p.id_pob = zp.id_pob
        LEFT JOIN hr_zamestnanec_zarazeni zz
            ON zz.id_zamestnanec = z.id_zamestnanec
           AND zz.platny = 1
           AND zz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = zz.id_slot
        ORDER BY z.id_zamestnanec DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalize_employee_row($row);
    }
    $stmt->close();

    return $rows;
}

function hr_fetch_employee(mysqli $db, int $id): ?array
{
    $sql = "
        SELECT
            z.id_zamestnanec,
            z.osobni_cislo,
            z.stav,
            z.zadano,
            ou.jmeno,
            ou.prijmeni,
            ou.datum_narozeni,
            ou.rodne_cislo,
            ou.pohlavi,
            ou.statni_obcanstvi,
            ou.misto_narozeni,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            pv.uvazek,
            pv.hodin_tydne,
            pv.doba_urcita,
            pv.delka_zk_doby,
            p.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.kod AS vztah_kod,
            pvt.nazev AS vztah_nazev,
            tel.telefon,
            em.email
        FROM hr_zamestnanec z
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_zamestnanec = z.id_zamestnanec
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_zamestnanec = z.id_zamestnanec
           AND pv.platny = 1
        LEFT JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_zamestnanec_pracoviste zp
            ON zp.id_zamestnanec = z.id_zamestnanec
           AND zp.platny = 1
           AND zp.hlavni = 1
        LEFT JOIN pobocka p
            ON p.id_pob = zp.id_pob
        LEFT JOIN hr_zamestnanec_zarazeni zz
            ON zz.id_zamestnanec = z.id_zamestnanec
           AND zz.platny = 1
           AND zz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = zz.id_slot
        LEFT JOIN hr_telefon tel
            ON tel.id_zamestnanec = z.id_zamestnanec
           AND tel.platny = 1
           AND tel.hlavni = 1
        LEFT JOIN hr_email em
            ON em.id_zamestnanec = z.id_zamestnanec
           AND em.platny = 1
           AND em.hlavni = 1
        WHERE z.id_zamestnanec = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? hr_normalize_employee_row($row) : null;
}

function hr_normalize_employee_row(array $row): array
{
    $jmeno = trim((string)($row['jmeno'] ?? ''));
    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
    $fullName = trim($prijmeni . ' ' . $jmeno);

    return $row + [
        'cele_jmeno' => $fullName !== '' ? $fullName : 'Bez jména',
        'inicialy' => hr_initials($jmeno, $prijmeni),
        'stav_label' => hr_stav_label((string)($row['stav'] ?? '')),
        'stav_badge' => ((string)($row['stav'] ?? '')) === 'aktivni' ? 'success' : 'neutral',
    ];
}

function hr_initials(string $jmeno, string $prijmeni): string
{
    $a = mb_substr(trim($jmeno), 0, 1);
    $b = mb_substr(trim($prijmeni), 0, 1);
    $out = mb_strtoupper($a . $b);
    return $out !== '' ? $out : '?';
}

function hr_stav_label(string $stav): string
{
    return match ($stav) {
        'aktivni' => 'Aktivní',
        'preruseny' => 'Přerušený',
        'ukonceny' => 'Ukončený',
        default => 'Příprava',
    };
}

function hr_format_date(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }

    $ts = strtotime($date);
    return $ts === false ? '-' : date('j. n. Y', $ts);
}

function hr_insert_employee(mysqli $db, array $data, int $zadal): int
{
    if ($zadal <= 0) {
        throw new RuntimeException('Chybí přihlášený uživatel.');
    }

    $jmeno = trim((string)($data['jmeno'] ?? ''));
    $prijmeni = trim((string)($data['prijmeni'] ?? ''));
    $osobniCislo = trim((string)($data['osobni_cislo'] ?? ''));
    $stav = (string)($data['stav'] ?? 'priprava');
    $datumNastupu = trim((string)($data['datum_nastupu'] ?? ''));
    $idVztahTyp = (int)($data['id_pracovni_vztah_typ'] ?? 0);
    $idPob = (int)($data['id_pob'] ?? 0);
    $idSlot = (int)($data['id_slot'] ?? 0);
    $telefon = trim((string)($data['telefon'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));

    if ($jmeno === '' || $prijmeni === '') {
        throw new RuntimeException('Vyplňte jméno a příjmení.');
    }
    if (!in_array($stav, ['priprava', 'aktivni', 'preruseny', 'ukonceny'], true)) {
        $stav = 'priprava';
    }
    if ($datumNastupu === '' || strtotime($datumNastupu) === false) {
        throw new RuntimeException('Vyplňte datum nástupu.');
    }
    if ($idVztahTyp <= 0 || $idPob < 0 || $idSlot <= 0) {
        throw new RuntimeException('Vyberte typ vztahu, pobočku a zařazení.');
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('E-mail nemá platný tvar.');
    }

    $osobniCisloDb = $osobniCislo !== '' ? $osobniCislo : null;
    $emailTyp = 1;
    $telefonTyp = 1;
    $hlavni = 1;
    $platny = 1;

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('INSERT INTO hr_zamestnanec (osobni_cislo, stav, zadal, aktivni) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('ssi', $osobniCisloDb, $stav, $zadal);
        $stmt->execute();
        $idZamestnanec = (int)$db->insert_id;
        $stmt->close();

        $pohlavi = 'neuvedeno';
        $stmt = $db->prepare('INSERT INTO hr_osobni_udaje (id_zamestnanec, jmeno, prijmeni, pohlavi, zadal, platny) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('isssi', $idZamestnanec, $jmeno, $prijmeni, $pohlavi, $zadal);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO hr_pracovni_vztah (id_zamestnanec, id_pracovni_vztah_typ, datum_nastupu, zadal, platny) VALUES (?, ?, ?, ?, 1)');
        $stmt->bind_param('iisi', $idZamestnanec, $idVztahTyp, $datumNastupu, $zadal);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO hr_zamestnanec_pracoviste (id_zamestnanec, id_pob, hlavni, platnost_od, zadal, platny) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('iiisi', $idZamestnanec, $idPob, $hlavni, $datumNastupu, $zadal);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO hr_zamestnanec_zarazeni (id_zamestnanec, id_slot, hlavni, platnost_od, zadal, platny) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('iiisi', $idZamestnanec, $idSlot, $hlavni, $datumNastupu, $zadal);
        $stmt->execute();
        $stmt->close();

        if ($telefon !== '') {
            $telefonNorm = preg_replace('~\s+~', '', $telefon) ?? $telefon;
            $stmt = $db->prepare('INSERT INTO hr_telefon (id_zamestnanec, id_telefon_typ, telefon, telefon_normalizovany, hlavni, zadal, platny) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iissiii', $idZamestnanec, $telefonTyp, $telefon, $telefonNorm, $hlavni, $zadal, $platny);
            $stmt->execute();
            $stmt->close();
        }

        if ($email !== '') {
            $stmt = $db->prepare('INSERT INTO hr_email (id_zamestnanec, id_email_typ, email, hlavni, zadal, platny) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iisiii', $idZamestnanec, $emailTyp, $email, $hlavni, $zadal, $platny);
            $stmt->execute();
            $stmt->close();
        }

        $db->commit();
        return $idZamestnanec;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
