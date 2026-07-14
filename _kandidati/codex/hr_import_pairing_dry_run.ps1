$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

$xlsxPath = Resolve-Path 'admin_testy/reporty_google_testy/google_data/HR 2024.xlsx'
$closedUntil = [datetime]'2026-04-30'

function Normalize-Name {
    param([string] $Text)
    $s = $Text.Trim().ToLowerInvariant()
    $s = [regex]::Replace($s, '\s+', ' ')
    return $s
}

function Normalize-PlainName {
    param([string] $Text)
    $s = Normalize-Name $Text
    $normalized = $s.Normalize([Text.NormalizationForm]::FormD)
    $builder = [System.Text.StringBuilder]::new()
    foreach ($ch in $normalized.ToCharArray()) {
        $category = [Globalization.CharUnicodeInfo]::GetUnicodeCategory($ch)
        if ($category -ne [Globalization.UnicodeCategory]::NonSpacingMark) {
            [void]$builder.Append($ch)
        }
    }
    return $builder.ToString().Normalize([Text.NormalizationForm]::FormC)
}

function Swap-FirstLast {
    param([string] $Text)
    $parts = @(($Text.Trim() -split '\s+') | Where-Object { $_ -ne '' })
    if ($parts.Count -lt 2) { return '' }
    $last = $parts[$parts.Count - 1]
    $rest = @($parts[0..($parts.Count - 2)])
    return ($last + ' ' + ($rest -join ' ')).Trim()
}

function Surname-Candidates {
    param([string] $Text)
    $parts = @(($Text.Trim() -split '\s+') | Where-Object { $_ -ne '' })
    if ($parts.Count -eq 0) { return @() }
    return @($parts[0], $parts[$parts.Count - 1]) | Where-Object { $_ -ne '' } | Select-Object -Unique
}

function Is-ImportPersonName {
    param([string] $Text)
    $s = (Normalize-Name $Text)
    if ($s -eq '') { return $false }
    if ($s -match '^(celkem|suma|kurýr$|end$|radi$)') { return $false }
    if ($s -eq 'system.xml.xmlelement') { return $false }
    if ($s -notmatch ' ') { return $false }
    return $true
}

function Shared-ItemText {
    param($SharedItem)
    if ($null -eq $SharedItem) { return '' }
    return [string]$SharedItem.InnerText
}

function Add-Index {
    param(
        [hashtable] $Index,
        [string] $Key,
        [object] $User
    )
    if ($Key -eq '') { return }
    if (-not $Index.ContainsKey($Key)) {
        $Index[$Key] = @()
    }
    $Index[$Key] = @($Index[$Key]) + @($User)
}

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

function Cell-SharedText {
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
    if ($type -eq 'inlineStr' -and $null -ne $Cell.is -and $null -ne $Cell.is.t) {
        return [string]$Cell.is.t
    }
    return $value
}

function Cell-ValueByRef {
    param($Row, [string] $Ref)
    foreach ($cell in $Row.c) {
        if ([string]$cell.r -eq $Ref) {
            if ($null -ne $cell.v) { return [string]$cell.v }
            return ''
        }
    }
    return ''
}

function Excel-Date {
    param([string] $Value)
    $n = 0.0
    if (-not [double]::TryParse($Value, [System.Globalization.NumberStyles]::Float, [System.Globalization.CultureInfo]::InvariantCulture, [ref]$n)) {
        return $null
    }
    return ([datetime]'1899-12-30').AddDays($n)
}

function Find-Match {
    param(
        [string] $ImportName,
        [hashtable] $FullIndex,
        [hashtable] $ReverseIndex,
        [hashtable] $FullPlainIndex,
        [hashtable] $ReversePlainIndex,
        [hashtable] $AliasIndex,
        [hashtable] $AliasPlainIndex,
        [hashtable] $SurnameIndex,
        [hashtable] $SurnamePlainIndex,
        [hashtable] $ManualIdMap,
        [hashtable] $UsersById,
        [int[]] $BranchIds
    )

    $key = Normalize-Name $ImportName
    $plainKey = Normalize-PlainName $ImportName
    $candidates = @()
    $method = ''

    if ($ManualIdMap.ContainsKey($ImportName) -and $UsersById.ContainsKey([int]$ManualIdMap[$ImportName])) {
        return [pscustomobject]@{
            status = 'matched'
            method = 'bordel_smeny'
            user = $UsersById[[int]$ManualIdMap[$ImportName]]
        }
    }

    $swapped = Swap-FirstLast $ImportName
    $swappedKey = Normalize-Name $swapped
    $swappedPlainKey = Normalize-PlainName $swapped

    if ($FullIndex.ContainsKey($key)) {
        $candidates = @($FullIndex[$key])
        $method = 'jmeno_prijmeni'
    } elseif ($ReverseIndex.ContainsKey($key)) {
        $candidates = @($ReverseIndex[$key])
        $method = 'prijmeni_jmeno'
    } elseif ($FullIndex.ContainsKey($swappedKey)) {
        $candidates = @($FullIndex[$swappedKey])
        $method = 'prohozene_jmeno_prijmeni'
    } elseif ($FullPlainIndex.ContainsKey($plainKey)) {
        $candidates = @($FullPlainIndex[$plainKey])
        $method = 'bez_diakritiky_jmeno_prijmeni'
    } elseif ($ReversePlainIndex.ContainsKey($plainKey)) {
        $candidates = @($ReversePlainIndex[$plainKey])
        $method = 'bez_diakritiky_prijmeni_jmeno'
    } elseif ($FullPlainIndex.ContainsKey($swappedPlainKey)) {
        $candidates = @($FullPlainIndex[$swappedPlainKey])
        $method = 'bez_diakritiky_prohozene'
    } elseif ($AliasIndex.ContainsKey($key)) {
        $candidates = @($AliasIndex[$key])
        $method = 'alias'
    } elseif ($AliasPlainIndex.ContainsKey($plainKey)) {
        $candidates = @($AliasPlainIndex[$plainKey])
        $method = 'alias_bez_diakritiky'
    } else {
        foreach ($surname in (Surname-Candidates $ImportName)) {
            $surnameKey = Normalize-Name $surname
            if ($SurnameIndex.ContainsKey($surnameKey)) {
                $candidates = @($SurnameIndex[$surnameKey])
                $method = 'prijmeni'
                break
            }
        }
        if ($candidates.Count -eq 0) {
            foreach ($surname in (Surname-Candidates $ImportName)) {
                $surnamePlainKey = Normalize-PlainName $surname
                if ($SurnamePlainIndex.ContainsKey($surnamePlainKey)) {
                    $candidates = @($SurnamePlainIndex[$surnamePlainKey])
                    $method = 'prijmeni_bez_diakritiky'
                    break
                }
            }
        }
    }

    if ($candidates.Count -eq 1) {
        return [pscustomobject]@{
            status = 'matched'
            method = $method
            user = $candidates[0]
        }
    }
    if ($candidates.Count -gt 1) {
        $activeCandidates = @($candidates | Where-Object { [int]$_.aktivni -eq 1 })
        if ($activeCandidates.Count -eq 1) {
            return [pscustomobject]@{
                status = 'matched'
                method = $method + '_aktivni'
                user = $activeCandidates[0]
            }
        }
        if ($BranchIds.Count -gt 0) {
            $pool = if ($activeCandidates.Count -gt 0) { $activeCandidates } else { $candidates }
            $branchCandidates = @($pool | Where-Object {
                $userPobocky = @($_.pobocky)
                $hit = $false
                foreach ($idPob in $BranchIds) {
                    if ($userPobocky -contains $idPob) {
                        $hit = $true
                    }
                }
                $hit
            })
            if ($branchCandidates.Count -eq 1) {
                return [pscustomobject]@{
                    status = 'matched'
                    method = $method + '_pobocka'
                    user = $branchCandidates[0]
                }
            }
        }
        return [pscustomobject]@{
            status = 'ambiguous'
            method = $method
            users = $candidates
        }
    }
    return [pscustomobject]@{
        status = 'unmatched'
        method = ''
    }
}

$usersJson = & php _kandidati/codex/hr_users_export.php
$users = $usersJson | ConvertFrom-Json

$fullIndex = @{}
$reverseIndex = @{}
$fullPlainIndex = @{}
$reversePlainIndex = @{}
$aliasIndex = @{}
$aliasPlainIndex = @{}
$surnameIndex = @{}
$surnamePlainIndex = @{}
$usersById = @{}
foreach ($user in $users) {
    $full = Normalize-Name (($user.jmeno + ' ' + $user.prijmeni).Trim())
    $reverse = Normalize-Name (($user.prijmeni + ' ' + $user.jmeno).Trim())
    $fullPlain = Normalize-PlainName (($user.jmeno + ' ' + $user.prijmeni).Trim())
    $reversePlain = Normalize-PlainName (($user.prijmeni + ' ' + $user.jmeno).Trim())
    $alias = Normalize-Name ([string]$user.alias)
    $aliasPlain = Normalize-PlainName ([string]$user.alias)
    $surname = Normalize-Name ([string]$user.prijmeni)
    $surnamePlain = Normalize-PlainName ([string]$user.prijmeni)
    $usersById[[int]$user.id_user] = $user
    Add-Index $fullIndex $full $user
    Add-Index $reverseIndex $reverse $user
    Add-Index $fullPlainIndex $fullPlain $user
    Add-Index $reversePlainIndex $reversePlain $user
    Add-Index $aliasIndex $alias $user
    Add-Index $aliasPlainIndex $aliasPlain $user
    Add-Index $surnameIndex $surname $user
    Add-Index $surnamePlainIndex $surnamePlain $user
}

$manualIdMap = @{
    'Hegedüs Erik' = 291
    'Pekárková Milena' = 408
    'Pechová Barbora' = 431
    'Lanský Zoltan' = 385
    'Rothová Anastasia' = 234
    'Chlubnová Adéla' = 577
    'Eszenyiová Patrícia' = 493
    'Jáklová Dominika' = 464
    'Šarközi Dávid' = 469
    'Roth Stanislav ml.' = 3
    'Hegedüs Gergö' = 520
}

$branchMap = @{
    'Malešice' = 1
    'Chodov' = 2
    'Zličín' = 3
    'Bolevec' = 4
    'Libuš' = 5
    'Prosek' = 6
    'Výroba' = 7
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($xlsxPath)

$shared = @()
$sharedText = Get-ZipText $zip 'xl/sharedStrings.xml'
if ($sharedText) {
    [xml]$sharedXml = $sharedText
    foreach ($si in $sharedXml.sst.si) {
        $shared += (Shared-ItemText $si)
    }
}

[xml]$workbook = Get-ZipText $zip 'xl/workbook.xml'
[xml]$rels = Get-ZipText $zip 'xl/_rels/workbook.xml.rels'
$relTargets = @{}
foreach ($rel in $rels.Relationships.Relationship) {
    $relTargets[[string]$rel.Id] = 'xl/' + ([string]$rel.Target).TrimStart('/')
}

$entries = @()
$months = @()
foreach ($sheet in $workbook.workbook.sheets.sheet) {
    $sheetName = [string]$sheet.name
    if ($sheetName -notlike 'Mzdy*') { continue }
    if ($sheetName -eq 'Mzdy') { continue }
    if ($sheetName -eq '__TEMP_SORT_MZDY__') { continue }

    $relId = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    [xml]$sheetXml = Get-ZipText $zip $relTargets[$relId]
    $row1 = $sheetXml.worksheet.sheetData.row | Where-Object { [int]$_.r -eq 1 } | Select-Object -First 1
    $startRaw = Cell-ValueByRef $row1 'M1'
    $endRaw = Cell-ValueByRef $row1 'S1'
    if ($startRaw -eq '') {
        $startRaw = Cell-ValueByRef $row1 'U1'
        $endRaw = Cell-ValueByRef $row1 'AA1'
    }
    $dateFrom = Excel-Date $startRaw
    $dateTo = Excel-Date $endRaw
    if ($null -eq $dateFrom -or $dateFrom -gt $closedUntil) { continue }

    $months += [pscustomobject]@{
        sheet = $sheetName
        od = $dateFrom.ToString('yyyy-MM-dd')
        do = if ($null -ne $dateTo) { $dateTo.ToString('yyyy-MM-dd') } else { '' }
    }

    foreach ($row in $sheetXml.worksheet.sheetData.row) {
        $rowNum = [int]$row.r
        if ($rowNum -lt 5) { continue }
        $nameCell = $row.c | Where-Object { [string]$_.r -eq ('B' + $rowNum) } | Select-Object -First 1
        $personName = (Cell-SharedText $nameCell $shared).Trim()
        if (-not (Is-ImportPersonName $personName)) { continue }

        $branchIds = @()
        foreach ($col in @('C','D','E','F')) {
            $branchCell = $row.c | Where-Object { [string]$_.r -eq ($col + $rowNum) } | Select-Object -First 1
            $branchName = (Cell-SharedText $branchCell $shared).Trim()
            if ($branchMap.ContainsKey($branchName)) {
                $branchIds += [int]$branchMap[$branchName]
            }
        }

        $entries += [pscustomobject]@{
            sheet = $sheetName
            row = $rowNum
            od = $dateFrom.ToString('yyyy-MM-dd')
            do = if ($null -ne $dateTo) { $dateTo.ToString('yyyy-MM-dd') } else { '' }
            import_jmeno = $personName
            branch_ids = @($branchIds | Select-Object -Unique)
        }
    }
}

$zip.Dispose()

$uniqueNames = @{}
foreach ($entry in $entries) {
    $key = Normalize-Name $entry.import_jmeno
    if (-not $uniqueNames.ContainsKey($key)) {
        $uniqueNames[$key] = [pscustomobject]@{
            import_jmeno = $entry.import_jmeno
            count = 0
            months = @{}
            branch_ids = @{}
        }
    }
    $uniqueNames[$key].count++
    $uniqueNames[$key].months[$entry.od] = $true
    foreach ($idPob in @($entry.branch_ids)) {
        if ([int]$idPob -gt 0) {
            $uniqueNames[$key].branch_ids[[string]$idPob] = $true
        }
    }
}

$matched = @()
$unmatched = @()
$ambiguous = @()
$methodCounts = @{}
$matchedEntryCount = 0
$unmatchedEntryCount = 0
$ambiguousEntryCount = 0

foreach ($item in $uniqueNames.Values) {
    $branchIds = @($item.branch_ids.Keys | ForEach-Object { [int]$_ })
    $match = Find-Match $item.import_jmeno $fullIndex $reverseIndex $fullPlainIndex $reversePlainIndex $aliasIndex $aliasPlainIndex $surnameIndex $surnamePlainIndex $manualIdMap $usersById $branchIds
    $monthList = @($item.months.Keys | Sort-Object)
    if ($match.status -eq 'matched') {
        $matchedEntryCount += [int]$item.count
        $matched += [pscustomobject]@{
            import_jmeno = $item.import_jmeno
            id_user = $match.user.id_user
            user_jmeno = (($match.user.jmeno + ' ' + $match.user.prijmeni).Trim())
            method = $match.method
            count = $item.count
            months = ($monthList -join ',')
        }
        if (-not $methodCounts.ContainsKey($match.method)) { $methodCounts[$match.method] = 0 }
        $methodCounts[$match.method]++
    } elseif ($match.status -eq 'ambiguous') {
        $ambiguousEntryCount += [int]$item.count
        $ambiguous += [pscustomobject]@{
            import_jmeno = $item.import_jmeno
            method = $match.method
            users = (($match.users | ForEach-Object { ([string]$_.id_user + ':' + $_.jmeno + ' ' + $_.prijmeni) }) -join '; ')
            count = $item.count
            months = ($monthList -join ',')
        }
    } else {
        $unmatchedEntryCount += [int]$item.count
        $unmatched += [pscustomobject]@{
            import_jmeno = $item.import_jmeno
            count = $item.count
            months = ($monthList -join ',')
        }
    }
}

Write-Output 'HR IMPORT DRY-RUN - PAROVANI LIDI'
Write-Output ('Uzavrene mesice: ' + $months.Count)
Write-Output ('Radku osob v Excelu: ' + $entries.Count)
Write-Output ('Unikatnich jmen v Excelu: ' + $uniqueNames.Count)
Write-Output ('Sparovano unikatnich: ' + $matched.Count)
Write-Output ('Nesparovano unikatnich: ' + $unmatched.Count)
Write-Output ('Nejednoznacne unikatnich: ' + $ambiguous.Count)
Write-Output ('Sparovano radku: ' + $matchedEntryCount)
Write-Output ('Nesparovano radku: ' + $unmatchedEntryCount)
Write-Output ('Nejednoznacne radku: ' + $ambiguousEntryCount)
Write-Output ''

Write-Output 'Metody sparovani:'
foreach ($key in ($methodCounts.Keys | Sort-Object)) {
    $value = $methodCounts[$key]
    Write-Output ('- ' + $key + ': ' + $value)
}
Write-Output ''

Write-Output 'Mesice:'
foreach ($month in ($months | Sort-Object od)) {
    Write-Output ('- ' + $month.od + ' az ' + $month.do + ' | ' + $month.sheet)
}
Write-Output ''

Write-Output 'Nesparovana jmena:'
foreach ($item in ($unmatched | Sort-Object import_jmeno)) {
    Write-Output ('- ' + $item.import_jmeno + ' | vyskytu=' + $item.count + ' | mesice=' + $item.months)
}
if ($unmatched.Count -eq 0) {
    Write-Output '- zadna'
}
Write-Output ''

Write-Output 'Nejednoznacna jmena:'
foreach ($item in ($ambiguous | Sort-Object import_jmeno)) {
    Write-Output ('- ' + $item.import_jmeno + ' | ' + $item.users)
}
if ($ambiguous.Count -eq 0) {
    Write-Output '- zadna'
}
