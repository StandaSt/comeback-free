$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

$file = Resolve-Path 'admin_testy/reporty_google_testy/google_data/HR 2024.xlsx'
$zip = [System.IO.Compression.ZipFile]::OpenRead($file)

function Get-ZipText {
    param(
        [System.IO.Compression.ZipArchive] $Zip,
        [string] $Path
    )
    $entry = $Zip.GetEntry($Path)
    if ($null -eq $entry) {
        return $null
    }
    $stream = $entry.Open()
    try {
        $reader = [System.IO.StreamReader]::new($stream, [System.Text.Encoding]::UTF8)
        try {
            return $reader.ReadToEnd()
        } finally {
            $reader.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

function Col-ToNum {
    param([string] $Col)
    $n = 0
    foreach ($ch in $Col.ToUpper().ToCharArray()) {
        $n = ($n * 26) + ([int][char]$ch - 64)
    }
    return $n
}

function Cell-Value {
    param(
        [xml] $SheetXml,
        $Cell,
        [string[]] $Shared
    )
    $type = [string]$Cell.t
    $valueNode = $Cell.v
    $value = if ($null -ne $valueNode) { [string]$valueNode } else { '' }
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
    return $value
}

$sharedXmlText = Get-ZipText $zip 'xl/sharedStrings.xml'
$shared = @()
if ($sharedXmlText) {
    [xml]$sharedXml = $sharedXmlText
    foreach ($si in $sharedXml.sst.si) {
        if ($null -ne $si.t) {
            $shared += [string]$si.t
        } elseif ($null -ne $si.r) {
            $txt = ''
            foreach ($r in $si.r) {
                if ($null -ne $r.t) {
                    $txt += [string]$r.t
                }
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

$ns = New-Object System.Xml.XmlNamespaceManager($workbook.NameTable)
$ns.AddNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')

Write-Output 'Soubor: HR 2024.xlsx'
Write-Output ('Listu: ' + $workbook.workbook.sheets.sheet.Count)
Write-Output ''

foreach ($sheet in $workbook.workbook.sheets.sheet) {
    $relId = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    $path = $relTargets[$relId]
    [xml]$sheetXml = Get-ZipText $zip $path
    $dimension = if ($sheetXml.worksheet.dimension) { [string]$sheetXml.worksheet.dimension.ref } else { '' }
    Write-Output ('--- ' + [string]$sheet.name + ' | ' + $dimension)

    $printed = 0
    foreach ($row in $sheetXml.worksheet.sheetData.row) {
        $rowNum = [int]$row.r
        if ($rowNum -gt 14) {
            break
        }

        $cells = @()
        foreach ($cell in $row.c) {
            $ref = [string]$cell.r
            if ($ref -notmatch '^([A-Z]+)([0-9]+)$') {
                continue
            }
            $colNum = Col-ToNum $Matches[1]
            if ($colNum -gt 32) {
                continue
            }
            $val = (Cell-Value $sheetXml $cell $shared).Trim()
            if ($val -ne '') {
                $cells += ($ref + '=' + $val)
            }
        }
        if ($cells.Count -gt 0) {
            Write-Output ('r' + $rowNum + ': ' + ($cells -join ' | '))
            $printed++
        }
    }

    if ($printed -eq 0) {
        Write-Output '(bez hodnot v prvnich 14 radcich)'
    }
    Write-Output ''
}

$zip.Dispose()
