<?php
declare(strict_types=1);

/**
 * DB dotazy pro dokumenty zobrazovane v HR prehledu.
 * Soucty, seznam a prime otevreni omezuje jednotnym osobnim rozsahem HR.
 * U importovanych smluv vraci take podpisy, casti a konkretni upozorneni pro personalistu.
 * Word otevre jako odvozeny PDF nahled; puvodni soubor zustava beze zmeny.
 */

/**
 * Vrati souhrn dokumentu a chybejicich podkladu u aktivnich zamestnancu.
 * Aktivni zamestnanec se pocita stejne jako v hornim prehledu: ma aktivni
 * osobu a alespon jeden aktualni pracovni vztah.
 */
require_once __DIR__ . '/hr_zamestnanci_pristup.php';

function hr_fetch_prehled_document_summary(mysqli $db): array
{
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    $scope = hr_zamestnanci_pobocka_sql($db, $idUser);
    $documentScope = hr_zamestnanci_pobocka_sql($db, $idUser, 'dp');
    $result = $db->query("
        SELECT
            (SELECT COUNT(*) FROM hr_dokument d INNER JOIN hr_person dp ON dp.id_person = d.id_person WHERE d.platny = 1 AND {$documentScope}) AS dokumentu_celkem,
            (SELECT COUNT(DISTINCT d.id_person) FROM hr_dokument d INNER JOIN hr_person dp ON dp.id_person = d.id_person WHERE d.platny = 1 AND {$documentScope}) AS osob_s_dokumenty,
            COUNT(*) AS aktivnich_zamestnancu,
            COALESCE(SUM(EXISTS(
                SELECT 1
                FROM hr_dokument d
                WHERE d.id_person = aktivni.id_person
                  AND d.id_dokument_typ = 2
                  AND d.platny = 1
            )), 0) AS se_smlouvou,
            COALESCE(SUM(EXISTS(
                SELECT 1
                FROM hr_dokument d
                WHERE d.id_person = aktivni.id_person
                  AND d.id_dokument_typ IN (3, 8, 9)
                  AND d.platny = 1
            )), 0) AS s_osobnim_dokladem,
            COALESCE(SUM(EXISTS(
                SELECT 1
                FROM hr_dokument d
                WHERE d.id_person = aktivni.id_person
                  AND d.id_dokument_typ = 10
                  AND d.platny = 1
            )), 0) AS s_kartickou_pojistovny,
            (SELECT COUNT(*)
             FROM hr_dokument d
             INNER JOIN hr_person dp ON dp.id_person = d.id_person AND dp.testovaci = 0
             LEFT JOIN (
                 SELECT DISTINCT p2.id_person
                 FROM hr_person p2
                 INNER JOIN hr_pracovni_vztah pv2
                     ON pv2.id_person = p2.id_person
                    AND pv2.platny = 1
                    AND (pv2.datum_ukonceni IS NULL OR pv2.datum_ukonceni >= CURDATE())
                 WHERE p2.aktivni = 1
                   AND p2.testovaci = 0
             ) a2 ON a2.id_person = d.id_person
             WHERE d.platny = 1 AND a2.id_person IS NULL AND {$documentScope}) AS dokumentu_mimo_aktivni,
            (SELECT COUNT(DISTINCT d.id_person)
             FROM hr_dokument d
             INNER JOIN hr_person dp ON dp.id_person = d.id_person AND dp.testovaci = 0
             LEFT JOIN (
                 SELECT DISTINCT p3.id_person
                 FROM hr_person p3
                 INNER JOIN hr_pracovni_vztah pv3
                     ON pv3.id_person = p3.id_person
                    AND pv3.platny = 1
                    AND (pv3.datum_ukonceni IS NULL OR pv3.datum_ukonceni >= CURDATE())
                 WHERE p3.aktivni = 1
                   AND p3.testovaci = 0
             ) a3 ON a3.id_person = d.id_person
             WHERE d.platny = 1 AND a3.id_person IS NULL AND {$documentScope}) AS osob_mimo_aktivni
        FROM (
            SELECT DISTINCT p.id_person
            FROM hr_person p
            INNER JOIN hr_pracovni_vztah pv
                ON pv.id_person = p.id_person
               AND pv.platny = 1
               AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
            WHERE p.aktivni = 1
              AND {$scope}
        ) aktivni
    ");
    $row = $result->fetch_assoc() ?: [];
    $result->free();

    $active = (int)($row['aktivnich_zamestnancu'] ?? 0);
    $withContract = (int)($row['se_smlouvou'] ?? 0);
    $withIdentityDocument = (int)($row['s_osobnim_dokladem'] ?? 0);
    $withInsuranceCard = (int)($row['s_kartickou_pojistovny'] ?? 0);

    return [
        'dokumentu_celkem' => (int)($row['dokumentu_celkem'] ?? 0),
        'osob_s_dokumenty' => (int)($row['osob_s_dokumenty'] ?? 0),
        'aktivnich_zamestnancu' => $active,
        'se_smlouvou' => $withContract,
        's_osobnim_dokladem' => $withIdentityDocument,
        's_kartickou_pojistovny' => $withInsuranceCard,
        'chybi_smlouva' => max(0, $active - $withContract),
        'chybi_osobni_doklad' => max(0, $active - $withIdentityDocument),
        'chybi_karticka_pojistovny' => max(0, $active - $withInsuranceCard),
        'dokumentu_mimo_aktivni' => (int)($row['dokumentu_mimo_aktivni'] ?? 0),
        'osob_mimo_aktivni' => (int)($row['osob_mimo_aktivni'] ?? 0),
    ];
}

/**
 * Nacte aktualni dokumenty zamestnancu, ktere smi prihlaseny uzivatel videt.
 */
function hr_fetch_employee_documents(mysqli $db, int $idUser, ?int $idPerson = null, int $limit = 2000): array
{
    // Hromadny prvni import presahuje200souboru; seznam nesmi skryvat vetsinu podkladu.
    $limit = max(1, min($limit, 5000));
    if ($idPerson !== null && !hr_zamestnanci_muze_osobu($db, $idUser, $idPerson)) {
        return [];
    }

    $wherePerson = $idPerson !== null ? ' AND d.id_person = ?' : '';
    // Rozsah se filtruje pred LIMITem, aby cizi soubory nevytlacily vlastni dokumenty.
    $scope = hr_zamestnanci_pobocka_sql($db, $idUser);
    $sql = "
        SELECT
            d.id_dokument,
            d.verze,
            d.id_person,
            d.nazev,
            d.platnost_od,
            d.platnost_do,
            d.vytvoreno,
            dt.nazev AS typ,
            ou.jmeno,
            ou.prijmeni,
            ds.puvodni_nazev,
            ds.ulozeny_nazev,
            ds.mime_typ,
            ds.velikost,
            du.poznamka AS import_poznamka,
            du.ocr_text AS import_udaje
        FROM hr_dokument d
        INNER JOIN hr_cis_dokument_typ dt
            ON dt.id_dokument_typ = d.id_dokument_typ
        INNER JOIN hr_person p
            ON p.id_person = d.id_person
           AND p.testovaci = 0
        INNER JOIN hr_osobni_udaje ou
            ON ou.id_person = p.id_person
           AND ou.platny = 1
        LEFT JOIN hr_dokument_soubor ds
            ON ds.id_dokument = d.id_dokument
           AND ds.verze = d.verze
           AND ds.poradi = 1
        LEFT JOIN hr_dokument_udaje du
            ON du.id_dokument = d.id_dokument AND du.verze = d.verze
        WHERE d.platny = 1
          AND d.id_person IS NOT NULL
          {$wherePerson}
          AND {$scope}
        ORDER BY d.vytvoreno DESC, d.id_dokument DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    if ($idPerson !== null) {
        $stmt->bind_param('ii', $idPerson, $limit);
    } else {
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rowPerson = (int)$row['id_person'];
        // Stare OCR poznamky nejsou importnim JSON; rozlisi je jednoznacny marker.
        $importMeta = json_decode((string)($row['import_poznamka'] ?? ''), true);
        $importMeta = is_array($importMeta) && ($importMeta['hr_import_smlouvy'] ?? 0) === 1 ? $importMeta : [];
        $rows[] = [
            'id_dokument' => (int)$row['id_dokument'],
            'verze' => (int)$row['verze'],
            'id_person' => $rowPerson,
            'nazev' => (string)$row['nazev'],
            'typ' => (string)$row['typ'],
            'osoba' => trim((string)$row['prijmeni'] . ' ' . (string)$row['jmeno']),
            'platnost_od' => $row['platnost_od'],
            'platnost_do' => $row['platnost_do'],
            'vytvoreno' => (string)$row['vytvoreno'],
            'puvodni_nazev' => (string)($row['puvodni_nazev'] ?? ''),
            'ulozeny_nazev' => (string)($row['ulozeny_nazev'] ?? ''),
            'mime_typ' => (string)($row['mime_typ'] ?? ''),
            'velikost' => $row['velikost'] === null ? null : (int)$row['velikost'],
            'import_meta' => $importMeta,
            'import_udaje' => $importMeta !== [] ? (string)($row['import_udaje'] ?? '') : '',
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Bezpecne odesle jeden soubor HR dokumentu po kontrole prava a firemniho pristupu.
 */
function hr_stream_employee_document(mysqli $db, int $idUser, int $idDocument, int $version): never
{
    $stmt = $db->prepare('
        SELECT d.id_person, ds.relativni_cesta, ds.puvodni_nazev, ds.mime_typ, ds.sha256
        FROM hr_dokument d
        INNER JOIN hr_person p ON p.id_person = d.id_person AND p.testovaci = 0
        INNER JOIN hr_dokument_soubor ds
            ON ds.id_dokument = d.id_dokument
           AND ds.verze = d.verze
           AND ds.poradi = 1
        WHERE d.id_dokument = ?
          AND d.verze = ?
          AND d.platny = 1
        LIMIT 1
    ');
    $stmt->bind_param('ii', $idDocument, $version);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $idPerson = is_array($row) ? (int)($row['id_person'] ?? 0) : 0;
    if ($idPerson <= 0 || !hr_zamestnanci_muze_osobu($db, $idUser, $idPerson)) {
        http_response_code(404);
        exit;
    }

    $documentsRoot = realpath(__DIR__ . '/../hr_dokumenty');
    $wwwRoot = realpath(dirname(__DIR__, 2));
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim((string)$row['relativni_cesta'], '/\\'));
    $path = $wwwRoot === false ? false : realpath($wwwRoot . DIRECTORY_SEPARATOR . $relative);
    if ($documentsRoot === false || $path === false
        || !str_starts_with($path, $documentsRoot . DIRECTORY_SEPARATOR)
        || !is_file($path)) {
        http_response_code(404);
        exit;
    }

    $name = basename((string)($row['puvodni_nazev'] ?? ''));
    if ($name === '' || $name === '.' || $name === '..') {
        $name = basename($path);
    }
    $mimeByExtension = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($extension, ['doc','docx'], true)) {
        require_once __DIR__.'/../hr_includes/hr_word_nahled.php';
        try {
            $path=hr_word_preview($path,(string)$row['sha256']);
        } catch (Throwable $error) {
            http_response_code(422);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Náhled dokumentu</title><h1>Dokument nelze zobrazit</h1><p>PDF náhled chybí nebo jeho kontrola selhala. Správce musí zkontrolovat balík hr_word_nahledy.zip a zapisovatelnost adresáře HR dokumentů.</p></html>';
            exit;
        }
        $extension='pdf'; $name=basename($path);
    }
    $mime = $mimeByExtension[$extension] ?? trim((string)($row['mime_typ'] ?? ''));
    if ($mime === '' || preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mime) !== 1) {
        $mime = 'application/octet-stream';
    }
    $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header("Content-Disposition: " . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($name));
    readfile($path);
    exit;
}
