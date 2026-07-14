<?php
// K21
// karty/porovnani_reportu.php * Verze: V7 * Aktualizace: 02.07.2026
declare(strict_types=1);

$card_min_html = '<p class="card_mini_text txt_seda">Kalendář reportů</p>';
$card_max_html = '';

$renderMode = isset($cbDashboardRenderMode) ? trim((string)$cbDashboardRenderMode) : '';

if ($renderMode === 'mini') {
    return;
}

$reportCalendarStart = '2026-06-16';
$reportCalendarEnd = (new DateTimeImmutable('today'))->format('Y-m-d');

$reportCalendarBranches = [
    1 => 'Malešice',
    2 => 'Chodov',
    3 => 'Zličín',
    4 => 'Prosek',
    5 => 'Libuš',
    6 => 'Bolevec',
];

$reportComparisonFieldLabels = [
    'reporty' => 'reporty',
    'reporty_is' => 'reporty_is',
    'reporty_pokladna' => 'reporty_pokladna',
    'reporty_is_pokladna' => 'reporty_is_pokladna',
    'reporty_restia' => 'reporty_restia',
    'reporty_is_restia' => 'reporty_is_restia',
];
$reportComparisonBaseIgnoredColumns = [
    'id_reportu' => true,
    'zdroj' => true,
    'zadal' => true,
    'zadano' => true,
    'editovano' => true,
    'platny' => true,
];
$reportComparisonDetailParamDate = trim((string)($_GET['pr_datum'] ?? ''));
$reportComparisonDetailParamBranch = (int)($_GET['pr_pob'] ?? 0);

$formatReportCalendarDate = static function (string $date): string {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format('d.m.Y');
    }
    return $date;
};

$normalizeReportComparisonValue = static function ($value): string {
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return trim((string)$value);
};

$fetchReportComparisonRow = static function (mysqli $conn, string $sql, string $types, array $params): ?array {
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

$collectReportComparisonDifferences = static function (?array $leftRow, ?array $rightRow, array $ignoredColumns = []) use ($normalizeReportComparisonValue): array {
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

        $leftValue = $normalizeReportComparisonValue($leftRow[$column] ?? null);
        $rightValue = $normalizeReportComparisonValue($rightRow[$column] ?? null);
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

$reportCalendarPairs = [];
$reportCalendarError = '';
$reportComparisonDetailMode = false;
$reportComparisonDetailError = '';
$reportComparisonDetailFields = [];
$reportComparisonDetailRows = [];
$reportComparisonDetailBranchName = '';
$reportComparisonDetailDateLabel = '';

try {
    $conn = db();
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    $branchIds = array_keys($reportCalendarBranches);
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));

    $sql = '
        SELECT DISTINCT
            g.datum_reportu,
            g.id_pob
        FROM reporty g
        INNER JOIN reporty_is i
            ON i.id_pob = g.id_pob
           AND i.datum_reportu = g.datum_reportu
           AND i.platny = 1
        WHERE g.zdroj = 1
          AND g.platny = 1
          AND g.datum_reportu >= ?
          AND g.datum_reportu <= ?
          AND g.id_pob IN (' . $placeholders . ')
        ORDER BY g.datum_reportu DESC, g.id_pob ASC
    ';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Kalendář reportů se nepodařilo připravit.');
    }

    $types = 'ss' . str_repeat('i', count($branchIds));
    $params = [$reportCalendarStart, $reportCalendarEnd];
    foreach ($branchIds as $idPob) {
        $params[] = $idPob;
    }

    $bindValues = [];
    $bindValues[] = $types;
    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $datum = (string)($row['datum_reportu'] ?? '');
            $idPob = (int)($row['id_pob'] ?? 0);
            if ($datum !== '' && isset($reportCalendarBranches[$idPob])) {
                if (!isset($reportCalendarPairs[$datum])) {
                    $reportCalendarPairs[$datum] = [];
                }
                $reportCalendarPairs[$datum][$idPob] = true;
            }
        }
        $result->free();
    }
    $stmt->close();

    $reportComparisonDetailMode = (
        $reportComparisonDetailParamDate !== ''
        && preg_match('~^\d{4}-\d{2}-\d{2}$~', $reportComparisonDetailParamDate) === 1
        && isset($reportCalendarBranches[$reportComparisonDetailParamBranch])
    );

    if ($reportComparisonDetailMode) {
        $reportComparisonDetailBranchName = $reportCalendarBranches[$reportComparisonDetailParamBranch];
        $reportComparisonDetailDateLabel = $formatReportCalendarDate($reportComparisonDetailParamDate);

        $reportyRow = $fetchReportComparisonRow(
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
            [$reportComparisonDetailParamBranch, $reportComparisonDetailParamDate]
        );
        $reportyIsRow = $fetchReportComparisonRow(
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
            [$reportComparisonDetailParamBranch, $reportComparisonDetailParamDate]
        );

        if (!is_array($reportyRow) || !is_array($reportyIsRow)) {
            throw new RuntimeException('Pro zvolený den nebo pobočku chybí dvojice reportů.');
        }

        $reportyId = (int)($reportyRow['id_reportu'] ?? 0);
        $reportyIsId = (int)($reportyIsRow['id_reportu'] ?? 0);
        if ($reportyId <= 0 || $reportyIsId <= 0) {
            throw new RuntimeException('Pro zvolený den nebo pobočku chybí ID reportu.');
        }

        $reportyPokladnaRow = $fetchReportComparisonRow(
            $conn,
            '
                SELECT *
                FROM reporty_pokladna
                WHERE id_reportu = ?
                LIMIT 1
            ',
            'i',
            [$reportyId]
        );
        $reportyIsPokladnaRow = $fetchReportComparisonRow(
            $conn,
            '
                SELECT *
                FROM reporty_is_pokladna
                WHERE id_reportu = ?
                LIMIT 1
            ',
            'i',
            [$reportyIsId]
        );
        $reportyRestiaRow = $fetchReportComparisonRow(
            $conn,
            '
                SELECT *
                FROM reporty_restia
                WHERE id_reportu = ?
                LIMIT 1
            ',
            'i',
            [$reportyId]
        );
        $reportyIsRestiaRow = $fetchReportComparisonRow(
            $conn,
            '
                SELECT *
                FROM reporty_is_restia
                WHERE id_reportu = ?
                LIMIT 1
            ',
            'i',
            [$reportyIsId]
        );

        $reportComparisonDetailFields = array_merge(
            $collectReportComparisonDifferences($reportyRow, $reportyIsRow, $reportComparisonBaseIgnoredColumns),
            $collectReportComparisonDifferences($reportyPokladnaRow, $reportyIsPokladnaRow, ['id_reportu' => true]),
            $collectReportComparisonDifferences($reportyRestiaRow, $reportyIsRestiaRow, ['id_reportu' => true])
        );

        $reportComparisonDetailRows = [
            [
                'label' => $reportComparisonFieldLabels['reporty'],
                'values' => [],
            ],
            [
                'label' => $reportComparisonFieldLabels['reporty_is'],
                'values' => [],
            ],
        ];

        if ($reportComparisonDetailFields !== []) {
            foreach ($reportComparisonDetailFields as $column => $values) {
                $reportComparisonDetailRows[0]['values'][$column] = (string)($values['left'] ?? '');
                $reportComparisonDetailRows[1]['values'][$column] = (string)($values['right'] ?? '');
            }
        } else {
            $reportComparisonDetailRows[0]['values']['_empty'] = '';
            $reportComparisonDetailRows[1]['values']['_empty'] = '';
        }
    }
} catch (Throwable $e) {
    if ($reportComparisonDetailMode) {
        $reportComparisonDetailError = trim($e->getMessage());
    } else {
        $reportCalendarError = trim($e->getMessage());
    }
}

ob_start();
?>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <?php if ($reportComparisonDetailMode): ?>
    <p class="card_text txt_seda odstup_vnejsi_0">Porovnání reportů pro <?= htmlspecialchars($reportComparisonDetailBranchName, ENT_QUOTES, 'UTF-8') ?> dne <?= htmlspecialchars($reportComparisonDetailDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($reportComparisonDetailError !== ''): ?>
      <p class="card_text txt_cervena"><?= htmlspecialchars($reportComparisonDetailError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;">zdroj</th>
            <?php foreach (array_keys($reportComparisonDetailRows[0]['values']) as $column): ?>
              <th style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;"><?= $column === '_empty' ? '' : htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reportComparisonDetailRows as $detailRow): ?>
            <tr>
              <td style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;"><?= htmlspecialchars((string)$detailRow['label'], ENT_QUOTES, 'UTF-8') ?></td>
              <?php foreach ($detailRow['values'] as $value): ?>
                <td style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;"><?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php else: ?>
    <p class="card_text txt_seda odstup_vnejsi_0">Kalendář reportů od <?= htmlspecialchars($formatReportCalendarDate($reportCalendarStart), ENT_QUOTES, 'UTF-8') ?> do <?= htmlspecialchars($formatReportCalendarDate($reportCalendarEnd), ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($reportCalendarError !== ''): ?>
      <p class="card_text txt_cervena"><?= htmlspecialchars($reportCalendarError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;"></th>
            <?php foreach ($reportCalendarBranches as $idPob => $branchName): ?>
              <?php
              $detailUrl = cb_url_query('/lib/porovnani_reportu_detail.php', [
                  'pr_od' => $reportCalendarStart,
                  'pr_do' => $reportCalendarEnd,
                  'pr_pob' => (string)$idPob,
              ]);
              ?>
              <th style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;">
                <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">detail</a>
              </th>
            <?php endforeach; ?>
          </tr>
          <tr>
            <th style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;">datum</th>
            <?php foreach ($reportCalendarBranches as $branchName): ?>
              <th style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;"><?= htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $dateCursor = new DateTimeImmutable($reportCalendarEnd);
          $dateStart = new DateTimeImmutable($reportCalendarStart);
          while ($dateCursor >= $dateStart):
              $dateValue = $dateCursor->format('Y-m-d');
          ?>
            <tr>
              <td style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;"><?= htmlspecialchars($dateCursor->format('d.m.Y'), ENT_QUOTES, 'UTF-8') ?></td>
              <?php foreach ($reportCalendarBranches as $idPob => $branchName): ?>
                <?php $hasPair = !empty($reportCalendarPairs[$dateValue][$idPob]); ?>
                <td style="padding:4px 8px;white-space:nowrap;border-bottom:1px solid #ddd;">
                  <?= $hasPair ? htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') : '' ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php
              $dateCursor = $dateCursor->modify('-1 day');
          endwhile;
          ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
$card_max_html = (string)ob_get_clean();

// Počet řádků: 355
// karty/porovnani_reportu.php * Konec souboru
