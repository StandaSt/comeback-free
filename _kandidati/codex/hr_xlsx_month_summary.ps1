$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

$file = Resolve-Path 'admin_testy/reporty_google_testy/google_data/HR 2024.xlsx'
$zip = [System.IO.Compression.ZipFile]::OpenRead($file)

function Get-ZipText {
    param([System.IO.Compression.ZipArchive] $Zip, [string] $Path)
    $entry = $Zip.GetEntry($Path)
    if ($null -eq $entry) { return $null }
    $stream = $entry.Open()
    try {
        $reader = [System.IO.StreamReader]::new($stream, [System.Text.Encoding]::UTF8)
        try { return $reader.ReadToEnd() } finally { $reader.Dispose() }
    } finally {
        $stream.Dispose()
    }
}

function Excel-Date {
    param([string] $Value)
    $n = 0.0
    if (-not [double]::TryParse($Value, [System.Globalization.NumberStyles]::Float, [System.Globalization.CultureInfo]::InvariantCulture, [ref]$n)) {
        return ''
    }
    return ([datetime]'1899-12-30').AddDays($n).ToString('yyyy-MM-dd')
}

function Cell-Value {
    param($Row, [string] $Ref)
    foreach ($cell in $Row.c) {
        if ([string]$cell.r -eq $Ref) {
            if ($null -ne $cell.v) { return [string]$cell.v }
            return ''
        }
    }
    return ''
}

function Shared-Text {
    param($Cell, [string[]] $Shared)
    if ($null -eq $Cell) { return '' }
    $type = [string]$Cell.t
    $value = if ($null -ne $Cell.v) { [string]$Cell.v } else { '' }
    if ($type -eq 's') {
        $idx = 0
        if ([int]::TryParse($value, [ref]$idx) -and $idx -ge 0 -and $idx -lt $Shared.Count) {
            return $Shared[$idx]
        }
        return ''
    }
    return $value
}

$shared = @()
$sharedText = Get-ZipText $zip 'xl/sharedStrings.xml'
if ($sharedText) {
    [xml]$sharedXml = $sharedText
    foreach ($si in $sharedXml.sst.si) {
        if ($null -ne $si.t) {
            $shared += [string]$si.t
        } elseif ($null -ne $si.r) {
            $txt = ''
            foreach ($r in $si.r) {
                if ($null -ne $r.t) { $txt += [string]$r.t }
            }
            $shared += $txt
        } else {
            $shared += ''
        }
    }
}

[xml]$workbook = Get-ZipText $zip 'xl/workbook.xml'
[xml]$rels = Get-ZipText $zip 'xl/_rels/workbook.xml.rels'
$relTargets = @{}
foreach ($rel in $rels.Relationships.Relationship) {
    $relTargets[[string]$rel.Id] = 'xl/' + ([string]$rel.Target).TrimStart('/')
}

foreach ($sheet in $workbook.workbook.sheets.sheet) {
    $name = [string]$sheet.name
    if ($name -notlike 'Mzdy*') { continue }
    if ($name -eq '__TEMP_SORT_MZDY__') { continue }

    $relId = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    [xml]$sheetXml = Get-ZipText $zip $relTargets[$relId]
    $row1 = $sheetXml.worksheet.sheetData.row | Where-Object { [int]$_.r -eq 1 } | Select-Object -First 1
    $startRaw = Cell-Value $row1 'M1'
    $endRaw = Cell-Value $row1 'S1'
    if ($startRaw -eq '') {
        $startRaw = Cell-Value $row1 'U1'
        $endRaw = Cell-Value $row1 'AA1'
    }

    $dataRows = 0
    $names = @()
    foreach ($row in $sheetXml.worksheet.sheetData.row) {
        $rowNum = [int]$row.r
        if ($rowNum -lt 5) { continue }
        $nameCell = $row.c | Where-Object { [string]$_.r -eq ('B' + $rowNum) } | Select-Object -First 1
        $person = (Shared-Text $nameCell $shared).Trim()
        if ($person -ne '') {
            $dataRows++
            if ($names.Count -lt 4) { $names += $person }
        }
    }

    Write-Output ($name + "`t" + (Excel-Date $startRaw) + "`t" + (Excel-Date $endRaw) + "`trows=" + $dataRows + "`t" + ($names -join ', '))
}

$zip.Dispose()
