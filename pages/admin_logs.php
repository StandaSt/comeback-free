<?php
declare(strict_types=1);
// pages/admin_logs.php * Verze: V7 * Aktualizace: 8.2.2026 * Počet řádků: 251

/*
 * ADMIN – výpis diagnostiky Směny (pomocne/data_smeny.txt)
 *
 * V7 (8.2.2026):
 * - Zrušena introspection/auto/try/one tlačítka a práce se schema_cache.json.
 * - Zobrazuje jen 2 tabulky z txt:
 *   1) Přihlášený uživatel + povolené pobočky (userGetLogged + actionHistoryFindById: workingBranchNames, mainBranchName)
 *   2) Číselník rolí (shiftRoleTypeFindAll: id, name)
 * - Tabulky jsou „DB pohled“: u každého pole návrh typu.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$txtFile = __DIR__ . '/../pomocne/data_smeny.txt';

/** @return array{label:string, data_raw:string, data_mixed:mixed, ok:bool, explain:string, ts:string} */
function cb_parse_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '') return null;

    // formát: "YYYY-mm-dd HH:ii:ss | LABEL | DATA"
    $parts = explode(' | ', $line, 3);

    $ts = '';
    $label = 'TXT:UNKNOWN';
    $raw = $line;

    if (count($parts) >= 3) {
        $ts    = trim($parts[0]);
        $label = trim($parts[1]);
        $raw   = trim($parts[2]);
    }

    $mixed = null;
    if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
        $tmp = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $mixed = $tmp;
        }
    }

    $isError =
        str_contains($label, ':ERROR') ||
        str_contains($label, 'SMENY:FATAL');

    $ok = !$isError;

    $explain = cb_explain_label($label);

    return [
        'ts' => $ts,
        'label' => $label,
        'data_raw' => $raw,
        'data_mixed' => $mixed,
        'ok' => $ok,
        'explain' => $explain,
    ];
}

function cb_explain_label(string $label): string
{
    if ($label === 'RUN:LOGIN') return 'Automatický běh po přihlášení.';
    if ($label === 'USER:PROFILE') return 'Profil přihlášeného uživatele (userGetLogged).';
    if ($label === 'USER:BRANCHES') return 'Povolené pobočky + hlavní pobočka (actionHistoryFindById).';
    if ($label === 'ROLETYPE:LIST') return 'Číselník rolí (shiftRoleTypeFindAll).';
    if ($label === 'SMENY:ERROR') return 'Chyba ve čtení dat (např. chybí token).';
    if ($label === 'SMENY:FATAL') return 'Neočekávaná chyba.';
    if (str_contains($label, ':ERROR')) return 'Chyba při získávání dat.';
    return '';
}

function cb_db_type_for(string $field): string
{
    // Záměrně jednoduché: podle potřeby upravíme později.
    $map = [
        'id' => 'INT',
        'id_user' => 'INT',
        'email' => 'VARCHAR(255)',
        'name' => 'VARCHAR(100)',
        'surname' => 'VARCHAR(100)',
        'phoneNumber' => 'VARCHAR(30)',
        'active' => 'TINYINT(1)',
        'approved' => 'TINYINT(1)',
        'createTime' => 'DATETIME',
        'lastLoginTime' => 'DATETIME',
        'mainBranchName' => 'VARCHAR(255)',
        'workingBranchNames' => 'JSON',
    ];

    return $map[$field] ?? 'TEXT';
}

function cb_as_rows_assoc(array $assoc, string $prefix = ''): array
{
    $rows = [];
    foreach ($assoc as $k => $v) {
        $name = (string)$k;
        if ($prefix !== '') $name = $prefix . $name;

        if (is_array($v)) {
            $rows[] = ['field' => $name, 'value' => json_encode($v, JSON_UNESCAPED_UNICODE), 'type' => cb_db_type_for($name)];
            continue;
        }

        if (is_bool($v)) {
            $rows[] = ['field' => $name, 'value' => ($v ? '1' : '0'), 'type' => cb_db_type_for($name)];
            continue;
        }

        if ($v === null) {
            $rows[] = ['field' => $name, 'value' => 'NULL', 'type' => cb_db_type_for($name)];
            continue;
        }

        $rows[] = ['field' => $name, 'value' => (string)$v, 'type' => cb_db_type_for($name)];
    }
    return $rows;
}

function cb_pick(array $src, array $keys): array
{
    $out = [];
    foreach ($keys as $k) {
        if (array_key_exists($k, $src)) {
            $out[$k] = $src[$k];
        }
    }
    return $out;
}

$profile = null;
$branches = null;
$roleTypes = null;

if (is_file($txtFile)) {
    $lines = @file($txtFile, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $r = cb_parse_line((string)$line);
            if (!$r || !$r['ok']) continue;

            if ($r['label'] === 'USER:PROFILE' && is_array($r['data_mixed'])) {
                $profile = $r['data_mixed'];
                continue;
            }

            if ($r['label'] === 'USER:BRANCHES' && is_array($r['data_mixed'])) {
                $branches = $r['data_mixed'];
                continue;
            }

            if ($r['label'] === 'ROLETYPE:LIST' && is_array($r['data_mixed'])) {
                $roleTypes = $r['data_mixed'];
                continue;
            }
        }
    }
}

$rowsUser = [];
if (is_array($profile)) {
    $rowsUser = array_merge($rowsUser, cb_as_rows_assoc($profile));
}
if (is_array($branches)) {
    $b = cb_pick($branches, ['workingBranchNames', 'mainBranchName']);
    $rowsUser = array_merge($rowsUser, cb_as_rows_assoc($b));
}

$rowsRoles = [];
if (is_array($roleTypes)) {
    foreach ($roleTypes as $item) {
        if (!is_array($item)) continue;
        $rowsRoles[] = [
            'id' => (string)($item['id'] ?? ''),
            'name' => (string)($item['name'] ?? ''),
        ];
    }
}

?>
<section class="card">
    <h2>pages/admin_logs.php – Směny: data z txt (2 tabulky)</h2>

    <style>
        table.cb-admin-log { border-collapse: collapse; table-layout: auto; }
        table.cb-admin-log th, table.cb-admin-log td { border: 1px solid #cfcfcf; padding: 6px 8px; vertical-align: top; }
        .cb-note { margin: 8px 0 10px 0; }
        .cb-muted { color: #666; }
        .cb-pre { white-space: pre-wrap; margin: 0; }
        .cb-nowrap { white-space: nowrap; }
    </style>

    <p class="cb-note cb-muted">
        Tahle stránka nic nestahuje. Jen čte pomocne/data_smeny.txt, které se plní při přihlášení.
    </p>

    <h3>Tabulka 1 – přihlášený uživatel + pobočky (DB pohled)</h3>

    <?php if (!$rowsUser): ?>
        <p>V txt zatím nejsou data (USER:PROFILE / USER:BRANCHES).</p>
    <?php else: ?>
        <table class="cb-admin-log">
            <thead>
            <tr>
                <th class="cb-nowrap">Pole</th>
                <th>Hodnota</th>
                <th class="cb-nowrap">Návrh typ</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rowsUser as $row): ?>
                <tr>
                    <td class="cb-nowrap"><strong><?= h($row['field']) ?></strong></td>
                    <td><pre class="cb-pre"><?= h($row['value']) ?></pre></td>
                    <td class="cb-nowrap"><?= h($row['type']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Tabulka 2 – číselník rolí (shiftRoleTypeFindAll)</h3>

    <?php if (!$rowsRoles): ?>
        <p>V txt zatím nejsou data (ROLETYPE:LIST).</p>
    <?php else: ?>
        <table class="cb-admin-log">
            <thead>
            <tr>
                <th class="cb-nowrap">id (INT)</th>
                <th>name (VARCHAR(100))</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rowsRoles as $r): ?>
                <tr>
                    <td class="cb-nowrap"><?= h($r['id']) ?></td>
                    <td><?= h($r['name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
// pages/admin_logs.php * Verze: V7 * Aktualizace: 8.2.2026 * Počet řádků: 251