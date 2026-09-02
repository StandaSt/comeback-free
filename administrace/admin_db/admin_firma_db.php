<?php
declare(strict_types=1);

/* Kontroluje a transakčně ukládá firmu a její aktuální jednatele. */

function cb_admin_firma_ico_existuje(mysqli $db, string $ico): bool
{
    $stmt = $db->prepare('SELECT 1 FROM firma WHERE ico = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit kontrolu IČO.');
    }
    $stmt->bind_param('s', $ico);
    $stmt->execute();
    $existuje = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $existuje;
}

function cb_admin_firmy_nacti(mysqli $db): array
{
    $result = $db->query('
        SELECT
            f.id_firma,
            f.id_firma_nadrazena,
            f.ico,
            f.dic,
            f.obchodni_jmeno,
            f.pravni_forma,
            f.datum_vzniku,
            f.textova_adresa,
            f.datum_aktualizace_ares,
            f.ares_nacteno,
            f.platnost_od,
            f.platnost_do,
            f.zadal,
            f.zadano,
            f.aktivni,
            j.id_firma_jednatel,
            j.jmeno AS jednatel_jmeno,
            j.prijmeni AS jednatel_prijmeni,
            j.funkce AS jednatel_funkce,
            j.datum_zapisu AS jednatel_datum_zapisu,
            j.funkce_od AS jednatel_funkce_od,
            j.funkce_do AS jednatel_funkce_do,
            j.hlavni AS jednatel_hlavni,
            j.aktivni AS jednatel_aktivni
        FROM firma f
        LEFT JOIN firma_jednatel j ON j.id_firma = f.id_firma
        ORDER BY f.id_firma, j.hlavni DESC, j.aktivni DESC, j.prijmeni, j.jmeno
    ');
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Nelze načíst firmy v systému.');
    }

    $firmy = [];
    while ($row = $result->fetch_assoc()) {
        $idFirma = (int)$row['id_firma'];
        if (!isset($firmy[$idFirma])) {
            $firmy[$idFirma] = [
                'id_firma' => $idFirma,
                'id_firma_nadrazena' => $row['id_firma_nadrazena'] !== null ? (int)$row['id_firma_nadrazena'] : null,
                'ico' => (string)$row['ico'],
                'dic' => $row['dic'],
                'obchodni_jmeno' => (string)$row['obchodni_jmeno'],
                'pravni_forma' => $row['pravni_forma'],
                'datum_vzniku' => $row['datum_vzniku'],
                'textova_adresa' => $row['textova_adresa'],
                'datum_aktualizace_ares' => $row['datum_aktualizace_ares'],
                'ares_nacteno' => (string)$row['ares_nacteno'],
                'platnost_od' => (string)$row['platnost_od'],
                'platnost_do' => $row['platnost_do'],
                'zadal' => (int)$row['zadal'],
                'zadano' => (string)$row['zadano'],
                'aktivni' => (int)$row['aktivni'] === 1,
                'jednatele' => [],
            ];
        }

        if ($row['id_firma_jednatel'] !== null) {
            $firmy[$idFirma]['jednatele'][] = [
                'id_firma_jednatel' => (int)$row['id_firma_jednatel'],
                'jmeno' => (string)$row['jednatel_jmeno'],
                'prijmeni' => (string)$row['jednatel_prijmeni'],
                'funkce' => (string)$row['jednatel_funkce'],
                'datum_zapisu' => $row['jednatel_datum_zapisu'],
                'funkce_od' => $row['jednatel_funkce_od'],
                'funkce_do' => $row['jednatel_funkce_do'],
                'hlavni' => (int)$row['jednatel_hlavni'] === 1,
                'aktivni' => (int)$row['jednatel_aktivni'] === 1,
            ];
        }
    }
    $result->free();

    return array_values($firmy);
}

function cb_admin_firma_uloz(mysqli $db, array $firma, int $hlavniJednatel, int $idUser): int
{
    $jednatele = is_array($firma['jednatele'] ?? null) ? array_values($firma['jednatele']) : [];
    if ($idUser <= 0 || $jednatele === [] || !isset($jednatele[$hlavniJednatel])) {
        throw new RuntimeException('Nelze uložit firmu bez platného hlavního jednatele.');
    }

    $db->begin_transaction();
    try {
        $countResult = $db->query('SELECT COUNT(*) AS pocet FROM firma FOR UPDATE');
        if (!($countResult instanceof mysqli_result)) {
            throw new RuntimeException('Nelze ověřit pořadí firmy.');
        }
        $countRow = $countResult->fetch_assoc();
        $countResult->free();
        $pocetFirem = (int)($countRow['pocet'] ?? 0);
        $idFirmaNadrazena = $pocetFirem === 0 ? null : 1;

        if ($pocetFirem > 0) {
            $parentResult = $db->query('SELECT 1 FROM firma WHERE id_firma = 1 LIMIT 1');
            if (!($parentResult instanceof mysqli_result) || $parentResult->fetch_row() === null) {
                throw new RuntimeException('V systému chybí mateřská firma s ID 1.');
            }
            $parentResult->free();
        }

        $stmtFirma = $db->prepare('
            INSERT INTO firma (
                id_firma_nadrazena, ico, dic, obchodni_jmeno, pravni_forma, datum_vzniku,
                kod_statu, nazev_ulice, cislo_domovni, cislo_orientacni, nazev_obce,
                nazev_casti_obce, psc, textova_adresa, datum_aktualizace_ares,
                ares_nacteno, platnost_od, zadal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), CURDATE(), ?)
        ');
        if ($stmtFirma === false) {
            throw new RuntimeException('Nelze připravit uložení firmy.');
        }

        $ico = (string)$firma['ico'];
        $dic = $firma['dic'];
        $obchodniJmeno = (string)$firma['obchodni_jmeno'];
        $pravniForma = $firma['pravni_forma'];
        $datumVzniku = $firma['datum_vzniku'];
        $kodStatu = $firma['kod_statu'];
        $nazevUlice = $firma['nazev_ulice'];
        $cisloDomovni = $firma['cislo_domovni'];
        $cisloOrientacni = $firma['cislo_orientacni'];
        $nazevObce = $firma['nazev_obce'];
        $nazevCastiObce = $firma['nazev_casti_obce'];
        $psc = $firma['psc'];
        $textovaAdresa = $firma['textova_adresa'];
        $datumAktualizaceAres = $firma['datum_aktualizace_ares'];
        $stmtFirma->bind_param(
            'i' . str_repeat('s', 14) . 'i',
            $idFirmaNadrazena,
            $ico,
            $dic,
            $obchodniJmeno,
            $pravniForma,
            $datumVzniku,
            $kodStatu,
            $nazevUlice,
            $cisloDomovni,
            $cisloOrientacni,
            $nazevObce,
            $nazevCastiObce,
            $psc,
            $textovaAdresa,
            $datumAktualizaceAres,
            $idUser
        );
        $stmtFirma->execute();
        $idFirma = (int)$db->insert_id;
        $stmtFirma->close();

        if ($pocetFirem === 0 && $idFirma !== 1) {
            throw new RuntimeException('První firma nezískala očekávané ID 1.');
        }

        $stmtJednatel = $db->prepare('
            INSERT INTO firma_jednatel (
                id_firma, jmeno, prijmeni, funkce, datum_zapisu, funkce_od, funkce_do,
                hlavni, ares_nacteno, aktivni
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ');
        if ($stmtJednatel === false) {
            throw new RuntimeException('Nelze připravit uložení jednatelů.');
        }

        foreach ($jednatele as $index => $jednatel) {
            $jmeno = (string)$jednatel['jmeno'];
            $prijmeni = (string)$jednatel['prijmeni'];
            $funkce = (string)$jednatel['funkce'];
            $datumZapisu = $jednatel['datum_zapisu'];
            $funkceOd = $jednatel['funkce_od'];
            $funkceDo = $jednatel['funkce_do'];
            $hlavni = $index === $hlavniJednatel ? 1 : 0;
            $stmtJednatel->bind_param(
                'issssssi',
                $idFirma,
                $jmeno,
                $prijmeni,
                $funkce,
                $datumZapisu,
                $funkceOd,
                $funkceDo,
                $hlavni
            );
            $stmtJednatel->execute();
        }
        $stmtJednatel->close();

        $db->commit();
        return $idFirma;
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
            throw new RuntimeException('Firma se stejným IČO již v systému existuje.', 0, $e);
        }
        throw $e;
    }
}
