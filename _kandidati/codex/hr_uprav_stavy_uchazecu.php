<?php
declare(strict_types=1);

require __DIR__ . '/../../www/db/db_connect.php';

$db = db_connect();
$db->begin_transaction();

try {
    $nactiStavy = static function (mysqli $db): array {
        $stavy = [];
        $result = $db->query('SELECT id_uchazec_stav, kod FROM hr_uchazec_stav');
        while ($row = $result->fetch_assoc()) {
            $stavy[(string)$row['kod']] = (int)$row['id_uchazec_stav'];
        }
        $result->free();
        return $stavy;
    };

    $stavyPred = $nactiStavy($db);

    $zajistiStav = static function (mysqli $db, array $stavy, string $kod, string $staryKod, string $nazev, int $poradi, int $konecny): int {
        if (isset($stavy[$kod])) {
            $id = $stavy[$kod];
            $stmt = $db->prepare('UPDATE hr_uchazec_stav SET nazev = ?, poradi = ?, konecny = ?, aktivni = 1 WHERE id_uchazec_stav = ?');
            $stmt->bind_param('siii', $nazev, $poradi, $konecny, $id);
            $stmt->execute();
            $stmt->close();
            return $id;
        }

        if ($staryKod !== '' && isset($stavy[$staryKod])) {
            $id = $stavy[$staryKod];
            $stmt = $db->prepare('UPDATE hr_uchazec_stav SET kod = ?, nazev = ?, poradi = ?, konecny = ?, aktivni = 1 WHERE id_uchazec_stav = ?');
            $stmt->bind_param('ssiii', $kod, $nazev, $poradi, $konecny, $id);
            $stmt->execute();
            $stmt->close();
            return $id;
        }

        $stmt = $db->prepare('INSERT INTO hr_uchazec_stav (kod, nazev, poradi, konecny, aktivni, zadal, zadano) VALUES (?, ?, ?, ?, 1, 1, NOW())');
        $stmt->bind_param('ssii', $kod, $nazev, $poradi, $konecny);
        $stmt->execute();
        $id = (int)$db->insert_id;
        $stmt->close();
        return $id;
    };

    $idNovy = $zajistiStav($db, $stavyPred, 'novy', '', 'Nový', 10, 0);
    $idVProcesu = $zajistiStav($db, $stavyPred, 'v_procesu', '', 'V procesu', 20, 0);
    $idOdmitnut = $zajistiStav($db, $stavyPred, 'odmitnut', 'zamitnut', 'Odmítnut', 30, 1);
    $idNemaZajem = $zajistiStav($db, $stavyPred, 'nema_zajem', 'odmitl', 'Nemá zájem', 40, 1);
    $idNastoupil = $zajistiStav($db, $stavyPred, 'nastoupil', 'zamestnanec', 'Nastoupil', 50, 1);

    $stavyPo = $nactiStavy($db);
    $mapovani = [
        'kontaktovat' => $idVProcesu,
        'kontaktovan' => $idVProcesu,
        'pohovor_domluven' => $idVProcesu,
        'po_pohovoru' => $idVProcesu,
        'ceka_na_rozhodnuti' => $idVProcesu,
        'nastupni_dotaznik' => $idVProcesu,
        'dotaznik_vyplnen' => $idVProcesu,
        'prijat' => $idVProcesu,
        'zamitnut' => $idOdmitnut,
        'odmitl' => $idNemaZajem,
        'nereaguje' => $idNemaZajem,
        'archiv' => $idNemaZajem,
        'zamestnanec' => $idNastoupil,
    ];

    foreach ($mapovani as $puvodniKod => $novyId) {
        if (!isset($stavyPo[$puvodniKod])) {
            continue;
        }
        $puvodniId = $stavyPo[$puvodniKod];
        if ($puvodniId === $novyId) {
            continue;
        }
        $stmt = $db->prepare('UPDATE hr_uchazec SET id_uchazec_stav = ? WHERE id_uchazec_stav = ?');
        $stmt->bind_param('ii', $novyId, $puvodniId);
        $stmt->execute();
        $stmt->close();
    }

    $aktivniIds = [$idNovy, $idVProcesu, $idOdmitnut, $idNemaZajem, $idNastoupil];
    $placeholders = implode(',', array_fill(0, count($aktivniIds), '?'));
    $types = str_repeat('i', count($aktivniIds));
    $stmt = $db->prepare("UPDATE hr_uchazec_stav SET aktivni = 0 WHERE id_uchazec_stav NOT IN ($placeholders)");
    $stmt->bind_param($types, ...$aktivniIds);
    $stmt->execute();
    $stmt->close();

    $db->query('UPDATE hr_uchazec_stav SET poradi = 1000 + id_uchazec_stav WHERE aktivni = 0');

    $db->commit();

    $result = $db->query('SELECT id_uchazec_stav, kod, nazev, poradi, konecny, aktivni FROM hr_uchazec_stav ORDER BY poradi, id_uchazec_stav');
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    }
    $result->free();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
