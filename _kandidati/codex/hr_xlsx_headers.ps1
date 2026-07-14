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

function Cell-Text {
    param($Cell, [string[]] $Shared)
    $type = [string]$Cell.t
    $value = if ($null -ne $Cell.v) { [string]$Cell.v } else { '' }
    if ($type -eq 's') {
        $idx = 0
        if ([int]::TryParse($value, [ref]$idx) -and $idx -ge 0 -and $idx -lt $Shared.Count) {
            return $Shared[$idx]
        }
        return ''
    }
    if ($type -eq 'inlineStr' -and $null -ne $Cell.is -and $null -ne $Cell.is.t) {
        return [string]$Cell.is.t
    }
    if ($null -ne $Cell.f -and $value -eq '') {
        return '[formula]'
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
    $relId = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    $path = $relTargets[$relId]
    [xml]$sheetXml = Get-ZipText $zip $path
    $dimension = if ($sheetXml.worksheet.dimension) { [string]$sheetXml.worksheet.dimension.ref } else { '' }
    $headerRow = $sheetXml.worksheet.sheetData.row | Where-Object { [int]$_.r -eq 4 } | Select-Object -First 1
    $headers = @()
    if ($null -ne $headerRow) {
        foreach ($cell in $headerRow.c) {
            $val = (Cell-Text $cell $shared).Trim()
            if ($val -ne '') {
                $headers += ([string]$cell.r + '=' + $val)
            }
        }
    }
    Write-Output ('--- ' + [string]$sheet.name + ' | ' + $dimension)
    Write-Output ($headers -join ' | ')
}

$zip.Dispose()
