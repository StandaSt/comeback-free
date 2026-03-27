<?php
// inicializace/google_data.php * Verze: V9 * Aktualizace: 27.03.2026

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../db/db_connect.php';

const BASE_DIR = __DIR__ . '/../admin_testy/google_data/Pobočky';
const MONTHS_EN = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];
const MONTHS_LOOKUP = [
    'january' => true,
    'february' => true,
    'march' => true,
    'april' => true,
    'may' => true,
    'june' => true,
    'july' => true,
    'august' => true,
    'september' => true,
    'october' => true,
    'november' => true,
    'december' => true,
];
const POBOCKY_MAP = [
    'Malešice' => 1,
    'Chodov' => 2,
    'Zličín' => 3,
    'Prosek' => 4,
    'Libuš' => 5,
    'Bolevec' => 6,
];

main();

function main(): void
{
    $scriptStart = microtime(true);

    if (!is_dir(BASE_DIR)) {
        fail('Nenalezena slozka: ' . BASE_DIR);
    }

    logLine('cas behu: 00:00');

    $db = db_connect();

    $pocetZpracovanychRadku = 0;
    $pocetUlozenychZaznamu = 0;
    $pocetChyb = 0;

    $pobocky = getBranchFolders(BASE_DIR);

    foreach ($pobocky as $nazevPob => $cestaPob) {
        logLine('========================================');
        logLine('pobocka: ' . $nazevPob);
        logLine('========================================');

        if ($nazevPob === 'Výroba') {
            $sesity = findWorkbooksForBranch($cestaPob, 'Výroba');
            foreach ($sesity as $sesit) {
                $nazevSouboru = basename($sesit);
                logLine('ctu sesit: ' . $nazevSouboru);

                $rok = extractYearFromWorkbookName($nazevSouboru, 'Výroba');
                if ($rok === null) {
                    continue;
                }

                $sheetTargets = getWorkbookSheetTargets($sesit);
                foreach ($sheetTargets as $sheetName => $sheetPath) {
                    if (!isMonthlySheetNameForYear($sheetName, $rok)) {
                        continue;
                    }

                    logLine('- list: ' . $sheetName);

                    $rows = getSheetRows($sesit, $sheetPath);
                    foreach ($rows as $rowNo => $row) {
                        if ($rowNo < 2) {
                            continue;
                        }

                        $pocetZpracovanychRadku++;

                        $zaznam = createRecord(
                            normalizeDate($row['A'] ?? ''),
                            trim((string)($row['C'] ?? '')),
                            normalizeTime($row['D'] ?? ''),
                            normalizeTime($row['E'] ?? ''),
                            normalizePauseTime($row['F'] ?? ''),
                            3,
                            0
                        );

                        if ($zaznam === null) {
                            continue;
                        }

                        if (insertReportRow($db, $zaznam)) {
                            $pocetUlozenychZaznamu++;
                            if ($zaznam['chyba'] === 1) {
                                $pocetChyb++;
                            }
                        }
                    }
                }

                logLine('cas behu: ' . formatElapsedRuntime($scriptStart));
            }

            logLine('');
            continue;
        }

        if (!isset(POBOCKY_MAP[$nazevPob])) {
            logLine('neznamy id_pob pro slozku: ' . $nazevPob);
            logLine('');
            continue;
        }

        $idPob = POBOCKY_MAP[$nazevPob];
        $sesity = findWorkbooksForBranch($cestaPob, $nazevPob);

        foreach ($sesity as $sesit) {
            $nazevSouboru = basename($sesit);
            logLine('ctu sesit: ' . $nazevSouboru);

            $rok = extractYearFromWorkbookName($nazevSouboru, $nazevPob);
            if ($rok === null) {
                continue;
            }

            $sheetTargets = getWorkbookSheetTargets($sesit);

            foreach ($sheetTargets as $sheetName => $sheetPath) {
                if (!isMonthlySheetNameForYear($sheetName, $rok)) {
                    continue;
                }

                logLine('- list: ' . $sheetName);

                $rows = getSheetRows($sesit, $sheetPath);

                foreach ($rows as $rowNo => $row) {
                    if ($rowNo < 2) {
                        continue;
                    }

                    $pocetZpracovanychRadku++;

                    $datum = normalizeDate($row['A'] ?? '');

                    $zaznamInstor = createRecord(
                        $datum,
                        trim((string)($row['C'] ?? '')),
                        normalizeTime($row['D'] ?? ''),
                        normalizeTime($row['E'] ?? ''),
                        normalizePauseTime($row['F'] ?? ''),
                        1,
                        $idPob
                    );

                    if ($zaznamInstor !== null) {
                        if (insertReportRow($db, $zaznamInstor)) {
                            $pocetUlozenychZaznamu++;
                            if ($zaznamInstor['chyba'] === 1) {
                                $pocetChyb++;
                            }
                        }
                    }

                    $zaznamKuryr = createRecord(
                        $datum,
                        trim((string)($row['H'] ?? '')),
                        normalizeTime($row['I'] ?? ''),
                        normalizeTime($row['J'] ?? ''),
                        normalizePauseTime($row['K'] ?? ''),
                        2,
                        $idPob
                    );

                    if ($zaznamKuryr !== null) {
                        if (insertReportRow($db, $zaznamKuryr)) {
                            $pocetUlozenychZaznamu++;
                            if ($zaznamKuryr['chyba'] === 1) {
                                $pocetChyb++;
                            }
                        }
                    }
                }
            }

            logLine('cas behu: ' . formatElapsedRuntime($scriptStart));
        }

        logLine('');
    }

    logLine('zpracovane radky: ' . $pocetZpracovanychRadku);
    logLine('ulozene zaznamy: ' . $pocetUlozenychZaznamu);
    logLine('pocet chyb: ' . $pocetChyb);
    logLine('celkova doba behu: ' . formatElapsedRuntime($scriptStart));

    echo 'Hotovo.';
}

function createRecord(?string $datum, string $fullName, ?string $casOd, ?string $casDo, ?string $pauza, int $idSlot, int $idPob): ?array
{
    if ($datum === null || $fullName === '') {
        return null;
    }

    if ($casOd === null && $casDo === null) {
        return null;
    }

    $rozsekane = splitName($fullName);
    if ($rozsekane === null) {
        return null;
    }

    $chyba = null;
    if (($casOd === null && $casDo !== null) || ($casOd !== null && $casDo === null)) {
        $chyba = 1;
    }

    return [
        'datum' => $datum,
        'id_pob' => $idPob,
        'jmeno' => $rozsekane['jmeno'],
        'prijmeni' => $rozsekane['prijmeni'],
        'id_slot' => $idSlot,
        'cas_od' => $casOd,
        'cas_do' => $casDo,
        'odpracovano' => computeWorkedHours($casOd, $casDo, $pauza),
        'pauza' => $pauza,
        'chyba' => $chyba,
    ];
}

function splitName(string $fullName): ?array
{
    $name = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
    if ($name === '') {
        return null;
    }

    $parts = preg_split('/\s+/u', $name);
    if (!is_array($parts) || $parts === []) {
        return null;
    }

    $prijmeni = (string)array_shift($parts);
    $jmeno = trim(implode(' ', $parts));

    return [
        'prijmeni' => mb_substr($prijmeni, 0, 50, 'UTF-8'),
        'jmeno' => mb_substr($jmeno, 0, 50, 'UTF-8'),
    ];
}

function insertReportRow(mysqli $db, array $r): bool
{
    $datum = $db->real_escape_string($r['datum']);
    $idPob = (int)$r['id_pob'];
    $jmeno = $db->real_escape_string($r['jmeno']);
    $prijmeni = $db->real_escape_string($r['prijmeni']);
    $idSlot = (int)$r['id_slot'];
    $pauza = $r['pauza'];
    $odpracovano = $r['odpracovano'];

    $casOdSql = $r['cas_od'] === null ? 'NULL' : "'" . $db->real_escape_string($r['cas_od']) . "'";
    $casDoSql = $r['cas_do'] === null ? 'NULL' : "'" . $db->real_escape_string($r['cas_do']) . "'";
    $pauzaSql = $pauza === null ? 'NULL' : "'" . $db->real_escape_string($pauza) . "'";
    $odpracovanoSql = $odpracovano === null ? 'NULL' : number_format((float)$odpracovano, 2, '.', '');
    $chybaSql = $r['chyba'] === null ? 'NULL' : (string)(int)$r['chyba'];

    $sql = "INSERT INTO smeny_report (datum, id_pob, jmeno, prijmeni, id_slot, cas_od, cas_do, odpracovano, pauza, chyba, zdroj)
            VALUES ('{$datum}', {$idPob}, '{$jmeno}', '{$prijmeni}', {$idSlot}, {$casOdSql}, {$casDoSql}, {$odpracovanoSql}, {$pauzaSql}, {$chybaSql}, 1)";

    if (!$db->query($sql)) {
        logLine('DB chyba: ' . $db->error);
        return false;
    }

    return true;
}

function computeWorkedHours(?string $casOd, ?string $casDo, ?string $pauza): ?float
{
    if ($casOd === null || $casDo === null) {
        return null;
    }

    $od = parseTimeToMinutes($casOd);
    $do = parseTimeToMinutes($casDo);

    if ($od === null || $do === null) {
        return null;
    }

    $diff = $do - $od;
    if ($diff < 0) {
        $diff += 24 * 60;
    }

    $pauzaMin = 0;
    if ($pauza !== null) {
        $pauzaParsed = parseTimeToMinutes($pauza);
        if ($pauzaParsed !== null) {
            $pauzaMin = $pauzaParsed;
        }
    }

    $worked = $diff - $pauzaMin;
    if ($worked < 0) {
        $worked = 0;
    }

    $h = intdiv($worked, 60);
    $m = $worked % 60;

    return (float)sprintf('%d.%02d', $h, $m);
}

function parseTimeToMinutes(string $time): ?int
{
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $time, $m) !== 1) {
        return null;
    }

    $h = (int)$m[1];
    $i = (int)$m[2];
    $s = (int)$m[3];

    if ($h < 0 || $h > 23 || $i < 0 || $i > 59 || $s < 0 || $s > 59) {
        return null;
    }

    return ($h * 60) + $i + (int)round($s / 60);
}

function isMonthlySheetNameForYear(string $sheetName, int $year): bool
{
    $name = trim($sheetName);
    $parts = preg_split('/\s+/', $name);
    if (!is_array($parts) || count($parts) !== 2) {
        return false;
    }

    $month = strtolower($parts[0]);
    $sheetYear = (int)$parts[1];

    if (!isset(MONTHS_LOOKUP[$month])) {
        return false;
    }

    return $sheetYear === $year;
}

function normalizeDate(string $value): ?string
{
    $v = trim($value);
    if ($v === '') {
        return null;
    }

    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $v, $m) === 1) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m) === 1) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    if (is_numeric($v)) {
        $days = (int)floor((float)$v);
        if ($days > 0) {
            $base = new DateTimeImmutable('1899-12-30');
            return $base->modify('+' . $days . ' days')->format('Y-m-d');
        }
    }

    return null;
}

function normalizeTime(string $value): ?string
{
    $v = trim($value);
    if ($v === '') {
        return null;
    }

    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m) === 1) {
        $h = (int)$m[1];
        $i = (int)$m[2];
        $s = isset($m[3]) ? (int)$m[3] : 0;

        if ($h === 24 && $i === 0 && $s === 0) {
            $h = 0;
        }

        if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59) {
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        return null;
    }

    if (is_numeric($v)) {
        $num = (float)$v;
        if ($num >= 0 && $num < 1) {
            $total = (int)round($num * 86400);
            if ($total >= 86400) {
                $total = 0;
            }

            $h = intdiv($total, 3600);
            $m = intdiv($total % 3600, 60);
            $s = $total % 60;

            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
    }

    return null;
}

function normalizePauseTime(string $value): ?string
{
    $v = trim($value);
    if ($v === '') {
        return null;
    }

    $asTime = normalizeTime($v);
    if ($asTime !== null) {
        return $asTime;
    }

    if (is_numeric($v)) {
        $num = (float)$v;
        if ($num >= 1) {
            $minutes = (int)round($num);
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return sprintf('%02d:%02d:00', $h, $m);
        }
    }

    return null;
}

function getBranchFolders(string $baseDir): array
{
    $out = [];
    $items = scandir($baseDir);

    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($full)) {
            continue;
        }

        $out[$item] = $full;
    }

    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

    return $out;
}

function findWorkbooksForBranch(string $branchDir, string $branchName): array
{
    $items = scandir($branchDir);
    if ($items === false) {
        return [];
    }

    $regex = '/^Databaze\s+' . preg_quote($branchName, '/') . '\s+([0-9]{4})\.xlsx$/iu';
    $matches = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $branchDir . DIRECTORY_SEPARATOR . $item;
        if (!is_file($full)) {
            continue;
        }

        if (preg_match($regex, $item, $m) !== 1) {
            continue;
        }

        $matches[] = [
            'year' => (int)$m[1],
            'path' => $full,
        ];
    }

    usort(
        $matches,
        static fn(array $a, array $b): int => $a['year'] <=> $b['year']
    );

    return array_values(array_map(static fn(array $row): string => $row['path'], $matches));
}

function extractYearFromWorkbookName(string $fileName, string $branchName): ?int
{
    $regex = '/^Databaze\s+' . preg_quote($branchName, '/') . '\s+([0-9]{4})\.xlsx$/iu';
    if (preg_match($regex, $fileName, $m) !== 1) {
        return null;
    }

    return (int)$m[1];
}

function getWorkbookSheetTargets(string $filePath): array
{
    $workbookXml = getZipEntryContent($filePath, 'xl/workbook.xml');
    $relsXml = getZipEntryContent($filePath, 'xl/_rels/workbook.xml.rels');

    if ($workbookXml === null || $relsXml === null) {
        return [];
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);

    if ($workbook === false || $rels === false) {
        return [];
    }

    $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $relMap = [];
    $relNodes = $rels->xpath('/p:Relationships/p:Relationship');
    if (is_array($relNodes)) {
        foreach ($relNodes as $node) {
            $attrs = $node->attributes();
            if ($attrs === null) {
                continue;
            }

            $id = trim((string)$attrs['Id']);
            $target = trim((string)$attrs['Target']);

            if ($id === '' || $target === '') {
                continue;
            }

            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . ltrim($target, '/');
            }

            $relMap[$id] = $target;
        }
    }

    $out = [];
    $sheetNodes = $workbook->xpath('/x:workbook/x:sheets/x:sheet');
    if (is_array($sheetNodes)) {
        foreach ($sheetNodes as $sheet) {
            $attrs = $sheet->attributes();
            $attrsR = $sheet->attributes('r', true);
            if ($attrs === null || $attrsR === null) {
                continue;
            }

            $name = trim((string)$attrs['name']);
            $rid = trim((string)$attrsR['id']);

            if ($name === '' || $rid === '') {
                continue;
            }

            if (!isset($relMap[$rid])) {
                continue;
            }

            $out[$name] = $relMap[$rid];
        }
    }

    return $out;
}

function getSheetRows(string $filePath, string $sheetPath): array
{
    $sheetXml = getZipEntryContent($filePath, $sheetPath);
    if ($sheetXml === null || $sheetXml === '') {
        return [];
    }

    $sharedStrings = getSharedStrings($filePath);

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        return [];
    }

    $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rowNodes = $sheet->xpath('/x:worksheet/x:sheetData/x:row');

    if (!is_array($rowNodes)) {
        return [];
    }

    $rows = [];

    foreach ($rowNodes as $rowNode) {
        $rowAttrs = $rowNode->attributes();
        if ($rowAttrs === null) {
            continue;
        }

        $rowNo = (int)$rowAttrs['r'];
        if ($rowNo <= 0) {
            continue;
        }

        $rows[$rowNo] = [];

        foreach ($rowNode->c as $cell) {
            $cellAttrs = $cell->attributes();
            if ($cellAttrs === null) {
                continue;
            }

            $ref = trim((string)$cellAttrs['r']);
            if ($ref === '') {
                continue;
            }

            if (preg_match('/^([A-Z]+)\d+$/', $ref, $m) !== 1) {
                continue;
            }

            $col = $m[1];
            $type = isset($cellAttrs['t']) ? (string)$cellAttrs['t'] : '';
            $value = '';

            if ($type === 's') {
                $idx = (int)$cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = trim((string)$cell->is->t);
            } else {
                $value = trim((string)$cell->v);
            }

            $rows[$rowNo][$col] = $value;
        }
    }

    return $rows;
}

function getSharedStrings(string $filePath): array
{
    $xml = getZipEntryContent($filePath, 'xl/sharedStrings.xml');
    if ($xml === null || $xml === '') {
        return [];
    }

    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        return [];
    }

    $doc->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $items = $doc->xpath('/x:sst/x:si');

    if (!is_array($items)) {
        return [];
    }

    $out = [];

    foreach ($items as $si) {
        $txt = '';

        if (isset($si->t)) {
            $txt = (string)$si->t;
        } elseif (isset($si->r)) {
            foreach ($si->r as $run) {
                $txt .= (string)$run->t;
            }
        }

        $out[] = trim($txt);
    }

    return $out;
}

function getZipEntryContent(string $filePath, string $entryPath): ?string
{
    if (!is_file($filePath)) {
        return null;
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $ok = $zip->open($filePath);
        if ($ok === true) {
            $content = $zip->getFromName($entryPath);
            $zip->close();

            if (is_string($content)) {
                return $content;
            }
        }
    }

    $escapedFile = str_replace("'", "''", $filePath);
    $escapedEntry = str_replace("'", "''", $entryPath);

    $script = <<<PS
\$ErrorActionPreference='Stop'
\$ProgressPreference='SilentlyContinue'
Add-Type -AssemblyName System.IO.Compression.FileSystem
\$zip=[System.IO.Compression.ZipFile]::OpenRead('$escapedFile')
try{
  \$entry=\$zip.GetEntry('$escapedEntry')
  if(\$null -eq \$entry){''; exit 0}
  \$stream=\$entry.Open()
  \$ms=New-Object System.IO.MemoryStream
  try{
    \$stream.CopyTo(\$ms)
    [Convert]::ToBase64String(\$ms.ToArray())
  } finally {
    \$stream.Close()
    \$ms.Dispose()
  }
} finally {
  \$zip.Dispose()
}
PS;

    $encodedScript = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . $encodedScript;
    $output = shell_exec($cmd);

    if (!is_string($output)) {
        return null;
    }

    $base64 = trim($output);
    if ($base64 === '') {
        return null;
    }

    $decoded = base64_decode($base64, true);
    if ($decoded === false) {
        return null;
    }

    return $decoded;
}

function logLine(string $text): void
{
    echo $text . "<br>\n";
    @ob_flush();
    flush();
}

function formatElapsedRuntime(float $scriptStart): string
{
    $elapsed = (int)max(0, floor(microtime(true) - $scriptStart));
    $minutes = intdiv($elapsed, 60);
    $seconds = $elapsed % 60;

    return sprintf('%02d:%02d', $minutes, $seconds);
}

function fail(string $message): void
{
    echo 'Chyba: ' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

// inicializace/google_data.php * Verze: V9 * Aktualizace: 27.03.2026
// počet řádků 815
