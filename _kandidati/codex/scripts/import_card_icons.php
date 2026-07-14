<?php
declare(strict_types=1);

require __DIR__ . '/../../db/db_connect.php';

$conn = db_connect();
$baseDir = realpath(__DIR__ . '/../../img/card_icons');
if ($baseDir === false) {
    fwrite(STDERR, "Chybi img/card_icons\n");
    exit(1);
}

$folders = glob($baseDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
if (!is_array($folders) || $folders === []) {
    fwrite(STDERR, "Nenalezeny zadne slozky s ikonami\n");
    exit(1);
}

usort($folders, static fn(string $a, string $b): int => strnatcasecmp(basename($a), basename($b)));

$rows = [];
foreach ($folders as $folderPath) {
    $folder = basename($folderPath);
    $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.svg');
    if (!is_array($files) || $files === []) {
        continue;
    }

    usort($files, static fn(string $a, string $b): int => strnatcasecmp(basename($a), basename($b)));

    foreach ($files as $filePath) {
        $file = basename($filePath);
        $name = preg_replace('~\.svg$~i', '', $file);
        if (!is_string($name) || $name === '') {
            continue;
        }

        $rows[] = [
            'nazev' => ucfirst(str_replace('_', ' ', $folder)) . ' ' . preg_replace('~^[^0-9]*_?~', '', $name),
            'soubor' => $folder . '/' . $file,
        ];
    }
}

if (count($rows) !== 200) {
    fwrite(STDERR, 'Ocekavano 200 ikon, nalezeno ' . count($rows) . PHP_EOL);
    exit(1);
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare('
        INSERT INTO card_icons (nazev, soubor, poradi, aktivni)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            nazev = VALUES(nazev),
            poradi = VALUES(poradi),
            aktivni = VALUES(aktivni)
    ');
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $poradi = 1;
    foreach ($rows as $row) {
        $nazev = $row['nazev'];
        $soubor = $row['soubor'];
        $stmt->bind_param('ssi', $nazev, $soubor, $poradi);
        $stmt->execute();
        $poradi++;
    }

    $stmt->close();
    $conn->commit();
    echo 'OK: ' . count($rows) . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
