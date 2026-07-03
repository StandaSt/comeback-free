<?php
// lib/porovnani_reportu_detail.php * Verze: V2 * Aktualizace: 02.07.2026
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/app.php';

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}

if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Porovnání reportů</title>
</head>
<body>
  <p>Nutné přihlášení.</p>
</body>
</html>
<?php
    exit;
}

$branchId = (int)($_GET['pr_pob'] ?? 0);
$dateFrom = trim((string)($_GET['pr_od'] ?? ''));
$dateTo = trim((string)($_GET['pr_do'] ?? ''));

$branches = [
    1 => 'Malešice',
    2 => 'Chodov',
    3 => 'Zličín',
    4 => 'Prosek',
    5 => 'Libuš',
    6 => 'Bolevec',
];

$fieldLabels = [
    'reporty' => 'reporty',
    'reporty_is' => 'reporty_is',
];

$ignoredBaseColumns = [
    'id_reportu' => true,
    'zdroj' => true,
    'zadal' => true,
    'zadano' => true,
    'editovano' => true,
    'platny' => true,
    'oteviral_text' => true,
    'zaviral_text' => true,
];

$percentColumns = [
    'col_pomer' => true,
    'nase_rozvozy_pozde_pomer' => true,
    'doruceno_vcas_pomer' => true,
    'woltdrive_zpozdene_pomer' => true,
];

$timeColumns = [
    'make_time_prumer_sec' => true,
];

$normalizeValue = static function ($value): string {
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return trim((string)$value);
};

$formatDate = static function (string $date): string {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTimeImmutable ? $dt->format('d.m.Y') : $date;
};

$formatDisplayValue = static function (string $column, $value) use ($percentColumns, $timeColumns): string {
    if ($value === null || $value === '') {
        return '';
    }

    if (isset($percentColumns[$column]) && is_numeric((string)$value)) {
        return number_format((float)$value * 100, 2, ',', ' ') . ' %';
    }

    if (isset($timeColumns[$column]) && is_numeric((string)$value)) {
        $seconds = (int)round((float)$value);
        $minutes = intdiv(max(0, $seconds), 60);
        $restSeconds = max(0, $seconds % 60);
        return $minutes . ':' . str_pad((string)$restSeconds, 2, '0', STR_PAD_LEFT);
    }

    return (string)$value;
};

$fetchRow = static function (mysqli $conn, string $sql, string $types, array $params): ?array {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Porovnání reportů se nepodařilo připravit.');
    }

    $bindValues = [];
    $bindValues[] = $types;
    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);

    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
};

$collectDifferences = static function (?array $leftRow, ?array $rightRow, array $ignoredColumns = []) use ($normalizeValue): array {
    $columns = [];
    if (is_array($leftRow)) {
        foreach (array_keys($leftRow) as $column) {
            $columns[$column] = true;
        }
    }
    if (is_array($rightRow)) {
        foreach (array_keys($rightRow) as $column) {
            $columns[$column] = true;
        }
    }

    $diffs = [];
    foreach (array_keys($columns) as $column) {
        if (isset($ignoredColumns[$column])) {
            continue;
        }

        $leftValue = $normalizeValue($leftRow[$column] ?? null);
        $rightValue = $normalizeValue($rightRow[$column] ?? null);
        if ($leftValue === $rightValue) {
            continue;
        }

        $diffs[$column] = [
            'left' => $leftValue,
            'right' => $rightValue,
        ];
    }

    return $diffs;
};

$error = '';
$branchName = $branches[$branchId] ?? '';
$daysWithDiffs = [];
$allColumns = [];

try {
    if ($branchName === '') {
        throw new RuntimeException('Chybí nebo nesedí pobočka.');
    }
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateFrom) !== 1 || preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateTo) !== 1) {
        throw new RuntimeException('Chybí nebo nesedí období.');
    }

    $fromDt = new DateTimeImmutable($dateFrom);
    $toDt = new DateTimeImmutable($dateTo);
    if ($toDt < $fromDt) {
        throw new RuntimeException('Období není platné.');
    }

    $conn = db();
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    $dateRows = [];
    $stmt = $conn->prepare('
        SELECT DISTINCT g.datum_reportu
        FROM reporty g
        INNER JOIN reporty_is i
            ON i.id_pob = g.id_pob
           AND i.datum_reportu = g.datum_reportu
           AND i.platny = 1
        WHERE g.id_pob = ?
          AND g.zdroj = 1
          AND g.platny = 1
          AND g.datum_reportu >= ?
          AND g.datum_reportu <= ?
        ORDER BY g.datum_reportu DESC
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze načíst dny porovnání.');
    }
    $stmt->bind_param('iss', $branchId, $dateFrom, $dateTo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $dateValue = trim((string)($row['datum_reportu'] ?? ''));
            if ($dateValue !== '') {
                $dateRows[] = $dateValue;
            }
        }
        $result->free();
    }
    $stmt->close();

    foreach ($dateRows as $dateValue) {
        $reportyRow = $fetchRow(
            $conn,
            '
                SELECT *
                FROM reporty
                WHERE id_pob = ?
                  AND datum_reportu = ?
                  AND zdroj = 1
                  AND platny = 1
                ORDER BY id_reportu DESC
                LIMIT 1
            ',
            'is',
            [$branchId, $dateValue]
        );
        $reportyIsRow = $fetchRow(
            $conn,
            '
                SELECT *
                FROM reporty_is
                WHERE id_pob = ?
                  AND datum_reportu = ?
                  AND platny = 1
                ORDER BY id_reportu DESC
                LIMIT 1
            ',
            'is',
            [$branchId, $dateValue]
        );

        if (!is_array($reportyRow) || !is_array($reportyIsRow)) {
            continue;
        }

        $reportyId = (int)($reportyRow['id_reportu'] ?? 0);
        $reportyIsId = (int)($reportyIsRow['id_reportu'] ?? 0);
        if ($reportyId <= 0 || $reportyIsId <= 0) {
            continue;
        }

        $reportyPokladnaRow = $fetchRow(
            $conn,
            'SELECT * FROM reporty_pokladna WHERE id_reportu = ? LIMIT 1',
            'i',
            [$reportyId]
        );
        $reportyIsPokladnaRow = $fetchRow(
            $conn,
            'SELECT * FROM reporty_is_pokladna WHERE id_reportu = ? LIMIT 1',
            'i',
            [$reportyIsId]
        );
        $reportyRestiaRow = $fetchRow(
            $conn,
            'SELECT * FROM reporty_restia WHERE id_reportu = ? LIMIT 1',
            'i',
            [$reportyId]
        );
        $reportyIsRestiaRow = $fetchRow(
            $conn,
            'SELECT * FROM reporty_is_restia WHERE id_reportu = ? LIMIT 1',
            'i',
            [$reportyIsId]
        );

        $diffs = array_merge(
            $collectDifferences($reportyRow, $reportyIsRow, $ignoredBaseColumns),
            $collectDifferences($reportyPokladnaRow, $reportyIsPokladnaRow, ['id_reportu' => true]),
            $collectDifferences($reportyRestiaRow, $reportyIsRestiaRow, ['id_reportu' => true])
        );

        if ($diffs === []) {
            continue;
        }

        $rowLeft = [];
        $rowRight = [];
        foreach ($diffs as $column => $values) {
            $rowLeft[$column] = $formatDisplayValue($column, $values['left'] ?? '');
            $rowRight[$column] = $formatDisplayValue($column, $values['right'] ?? '');
            $allColumns[$column] = true;
        }

        $daysWithDiffs[] = [
            'date' => $dateValue,
            'date_label' => $formatDate($dateValue),
            'rows' => [
                [
                    'label' => $fieldLabels['reporty'],
                    'values' => $rowLeft,
                ],
                [
                    'label' => $fieldLabels['reporty_is'],
                    'values' => $rowRight,
                ],
            ],
        ];
    }
} catch (Throwable $e) {
    $error = trim($e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Porovnání reportů</title>
  <style>
    body {
      margin: 0;
      padding: 16px;
      background: #f5f7fa;
      color: #1f2937;
      font: 14px/1.4 Arial, sans-serif;
    }

    .wrap {
      max-width: 100%;
    }

    .card {
      margin-bottom: 16px;
      padding: 12px;
      background: #ffffff;
      border: 1px solid #d6dbe3;
      border-radius: 12px;
      box-sizing: border-box;
    }

    .table-wrap {
      overflow: auto;
      max-width: 100%;
      max-height: calc(100vh - 140px);
      border: 1px solid #d6dbe3;
    }

    h1, p {
      margin: 0 0 12px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 4px 8px;
      white-space: nowrap;
      border: 1px solid #d6dbe3;
      text-align: left;
      vertical-align: top;
    }

    thead th {
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 2;
    }

    .datum-row td {
      font-weight: 700;
      background: #f3f6fa;
      text-align: left;
    }

    .separator-row td {
      height: 10px;
      padding: 0;
      border: 0;
      background: #ffffff;
    }

    .value-row td {
      text-align: right;
      background: #ffffff;
    }

    .value-row td:first-child {
      text-align: left;
      font-weight: 700;
      background: #fafafa;
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Porovnání reportů</h1>
    <?php if ($error !== ''): ?>
      <p><?= h($error) ?></p>
    <?php else: ?>
      <p>Pobočka <?= h($branchName) ?>, období <?= h($formatDate($dateFrom)) ?> až <?= h($formatDate($dateTo)) ?></p>
      <?php if ($daysWithDiffs === []): ?>
        <p>V uvedeném období nejsou žádné rozdíly.</p>
      <?php else: ?>
        <?php $columnList = array_keys($allColumns); ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>zdroj</th>
                <?php foreach ($columnList as $column): ?>
                  <th><?= h((string)$column) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daysWithDiffs as $day): ?>
                <tr class="datum-row">
                  <td colspan="<?= h((string)(count($columnList) + 1)) ?>"><?= h((string)$day['date_label']) ?></td>
                </tr>
                <?php foreach ((array)$day['rows'] as $row): ?>
                  <tr class="value-row">
                    <td><?= h((string)($row['label'] ?? '')) ?></td>
                    <?php foreach ($columnList as $column): ?>
                      <td><?= h((string)(($row['values'][$column] ?? ''))) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
                <tr class="separator-row">
                  <td colspan="<?= h((string)(count($columnList) + 1)) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
