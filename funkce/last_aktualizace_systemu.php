<?php
// funkce/last_aktualizace_systemu.php * Verze: V1 * Aktualizace: 30.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../db/db_connect.php';

function cb_last_aktualizace_systemu(): void
{
    $root = dirname(__DIR__);
    $nejnovejsiCas = 0;

    $soubory = [];
    $indexSoubor = $root . '/index.php';

    if (is_file($indexSoubor)) {
        $soubory[] = $indexSoubor;
    }

    $masky = [
        $root . '/db/*.php',
        $root . '/includes/*.php',
        $root . '/includes/loaders/*.php',
        $root . '/includes/hlavicka/*.php',
        $root . '/js/*.js',
        $root . '/ajax/*.php',
        $root . '/funkce/*.php',
        $root . '/karty/*.php',
        $root . '/mobil/*.php',
        $root . '/modaly/*.php',
    ];

    foreach ($masky as $maska) {
        $nalezeno = glob($maska);

        if ($nalezeno === false) {
            continue;
        }

        foreach ($nalezeno as $soubor) {
            if (is_file($soubor)) {
                $soubory[] = $soubor;
            }
        }
    }

    foreach ($soubory as $soubor) {
        $cas = filemtime($soubor);

        if ($cas === false) {
            continue;
        }

        if ($cas > $nejnovejsiCas) {
            $nejnovejsiCas = $cas;
        }
    }

    if ($nejnovejsiCas <= 0) {
        return;
    }

    $upravaSouboru = date('Y-m-d H:i:s', $nejnovejsiCas);

    $conn = db_connect();

    $sql = 'SELECT id_set, uprava_souboru FROM set_system ORDER BY id_set ASC LIMIT 1';
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $result->free();

    if ($row === null) {
        return;
    }

    $idSet = (int)$row['id_set'];
    $dbUpravaSouboru = (string)($row['uprava_souboru'] ?? '');

    if ($dbUpravaSouboru === $upravaSouboru) {
        return;
    }

    $stmt = $conn->prepare('UPDATE set_system SET uprava_souboru = ?, verze = verze + 1 WHERE id_set = ?');
    $stmt->bind_param('si', $upravaSouboru, $idSet);
    $stmt->execute();
    $stmt->close();
}

/* funkce/last_aktualizace_systemu.php V1 * počet řádků: 89 * aktualizace: 30.06.2026 */
// Konec souboru
