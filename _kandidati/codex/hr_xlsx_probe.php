<?php
declare(strict_types=1);

$file = __DIR__ . '/../../admin_testy/reporty_google_testy/google_data/HR 2024.xlsx';

if (!is_file($file)) {
    fwrite(STDERR, "Soubor nenalezen.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    fwrite(STDERR, "XLSX nejde otevrit jako ZIP.\n");
    exit(1);
}

function hrProbeXml(ZipArchive $zip, string $path): ?SimpleXMLElement
{
    $raw = $zip->getFromName($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    return simplexml_load_string($raw) ?: null;
}

function hrProbeColToNum(string $col): int
{
    $num = 0;
    $col = strtoupper($col);
    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
        $num = $num * 26 + (ord($col[$i]) - 64);
    }
    return $num;
}

function hrProbeCellPos(string $ref): array
{
    if (preg_match('/^([A-Z]+)([0-9]+)$/', strtoupper($ref), $m) !== 1) {
        return [0, 0];
    }
    return [hrProbeColToNum($m[1]), (int)$m[2]];
}

function hrProbeSharedStrings(ZipArchive $zip): array
{
    $xml = hrProbeXml($zip, 'xl/sharedStrings.xml');
    if (!$xml instanceof SimpleXMLElement) {
        return [];
    }

    $out = [];
    foreach ($xml->si as $si) {
        $parts = [];
        if (isset($si->t)) {
            $parts[] = (string)$si->t;
        }
        foreach ($si->r as $r) {
            $parts[] = (string)$r->t;
        }
        $out[] = implode('', $parts);
    }
    return $out;
}

function hrProbeCellValue(SimpleXMLElement $cell, array $shared): string
{
    $type = (string)($cell['t'] ?? '');
    $value = isset($cell->v) ? (string)$cell->v : '';
    if ($type === 's') {
        return $shared[(int)$value] ?? '';
    }
    if ($type === 'inlineStr' && isset($cell->is->t)) {
        return (string)$cell->is->t;
    }
    return $value;
}

$shared = hrProbeSharedStrings($zip);
$workbook = hrProbeXml($zip, 'xl/workbook.xml');
$rels = hrProbeXml($zip, 'xl/_rels/workbook.xml.rels');

$relTargets = [];
if ($rels instanceof SimpleXMLElement) {
    foreach ($rels->Relationship as $rel) {
        $relTargets[(string)$rel['Id']] = 'xl/' . ltrim((string)$rel['Target'], '/');
    }
}

$sheets = [];
if ($workbook instanceof SimpleXMLElement) {
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    foreach ($workbook->sheets->sheet as $sheet) {
        $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relId = (string)($attrs['id'] ?? '');
        $sheets[] = [
            'name' => (string)$sheet['name'],
            'path' => $relTargets[$relId] ?? '',
        ];
    }
}

echo 'Soubor: HR 2024.xlsx' . PHP_EOL;
echo 'Listu: ' . count($sheets) . PHP_EOL . PHP_EOL;

foreach ($sheets as $sheet) {
    $path = $sheet['path'];
    $xml = $path !== '' ? hrProbeXml($zip, $path) : null;
    if (!$xml instanceof SimpleXMLElement) {
        echo '--- ' . $sheet['name'] . ' | nelze cist' . PHP_EOL;
        continue;
    }

    $dimension = (string)($xml->dimension['ref'] ?? '');
    echo '--- ' . $sheet['name'] . ' | ' . $dimension . PHP_EOL;

    $rowsPrinted = 0;
    foreach ($xml->sheetData->row as $row) {
        $rowNum = (int)$row['r'];
        if ($rowNum > 12) {
            break;
        }

        $cells = [];
        foreach ($row->c as $cell) {
            [$colNum] = hrProbeCellPos((string)$cell['r']);
            if ($colNum <= 0 || $colNum > 30) {
                continue;
            }
            $value = trim(hrProbeCellValue($cell, $shared));
            if ($value !== '') {
                $cells[] = (string)$cell['r'] . '=' . $value;
            }
        }

        if ($cells !== []) {
            echo 'r' . $rowNum . ': ' . implode(' | ', $cells) . PHP_EOL;
            $rowsPrinted++;
        }
    }

    if ($rowsPrinted === 0) {
        echo '(bez hodnot v prvnich 12 radcich)' . PHP_EOL;
    }
    echo PHP_EOL;
}

$zip->close();
