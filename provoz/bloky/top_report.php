<?php
declare(strict_types=1);

(static function (): void {
    $tz = new DateTimeZone('Europe/Prague');
    $now = new DateTimeImmutable('now', $tz);
    $todayStart = new DateTimeImmutable($now->format('Y-m-d') . ' 06:00:00', $tz);
    $fallbackFrom = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;
    $fallbackTo = $now->modify('+1 second');
    $normalizeDateTime = static function (string $value): string {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return '';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $value, $m) === 1) {
            $value = $m[1] . '-' . $m[2] . '-' . $m[3] . ' 06:00:00';
        } elseif (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$~', $value) === 1) {
            $value .= ':00';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$~', $value, $m) !== 1) {
            return '';
        }
        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || (int)$m[4] > 23 || (int)$m[5] > 59 || (int)$m[6] > 59) {
            return '';
        }
        return $value;
    };
    $fromSql = $normalizeDateTime((string)($_SESSION['cb_obdobi_od'] ?? ''));
    $toSql = $normalizeDateTime((string)($_SESSION['cb_obdobi_do'] ?? ''));
    if ($fromSql === '' || $toSql === '' || $fromSql > $toSql) {
        $from = $fallbackFrom;
        $to = $fallbackTo;
        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $to->format('Y-m-d H:i:s');
    } else {
        $from = new DateTimeImmutable($fromSql, $tz);
        $to = new DateTimeImmutable($toSql, $tz);
    }
    $periodLabel = $from->format('j.n.Y') === $to->format('j.n.Y')
        ? $from->format('j.n.Y G:i') . ' - ' . $to->format('G:i')
        : $from->format('j.n.Y G:i') . ' - ' . $to->format('j.n.Y G:i');
    $selectedBranchIds = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
    if ($selectedBranchIds === []) {
        $fallbackBranchId = (int)($_SESSION['cb_pobocka_id'] ?? 0);
        if ($fallbackBranchId > 0) {
            $selectedBranchIds = [$fallbackBranchId];
        }
    }
    $selectedBranchIds = array_values(array_unique(array_filter(array_map('intval', $selectedBranchIds), static fn(int $id): bool => $id > 0)));
    sort($selectedBranchIds);
    $branchFilterSql = $selectedBranchIds !== [] ? ' AND o.id_pob IN (' . implode(',', $selectedBranchIds) . ')' : '';
    $branchLabel = '';
    $rows = [];
    $totalOrders = 0;
    $totalSales = 0.0;
    $maxSales = 0.0;
    $channelColors = [
        'Wolt' => '#2563eb',
        'Bolt' => '#22c55e',
        'Foodora' => '#f97316',
        'Vlastní web' => '#7c3aed',
        'Ruční zadání' => '#f59e0b',
        'Ostatní' => '#64748b',
    ];

    try {
        $conn = db();
        if ($selectedBranchIds !== []) {
            $allBranchIds = [];
            $cbUser = $_SESSION['cb_user'] ?? null;
            $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
            if ($idUser > 0 && function_exists('cb_pobocky_get_allowed_for_user')) {
                $allowed = cb_pobocky_get_allowed_for_user($idUser);
                $allBranchIds = array_values(array_unique(array_filter(array_map('intval', $allowed['ids'] ?? []), static fn(int $id): bool => $id > 0)));
                sort($allBranchIds);
            }

            if ($allBranchIds === [] || $selectedBranchIds !== $allBranchIds) {
                $branchNamesById = [];
                $branchSql = 'SELECT id_pob, nazev FROM pobocka WHERE id_pob IN (' . implode(',', $selectedBranchIds) . ') ORDER BY nazev ASC';
                $branchResult = $conn->query($branchSql);
                if ($branchResult instanceof mysqli_result) {
                    while ($branchRow = $branchResult->fetch_assoc()) {
                        $idPob = (int)($branchRow['id_pob'] ?? 0);
                        if ($idPob > 0) {
                            $branchNamesById[$idPob] = trim((string)($branchRow['nazev'] ?? ''));
                        }
                    }
                    $branchResult->free();
                }

                $branchNames = [];
                foreach ($selectedBranchIds as $idPob) {
                    $branchNames[] = ($branchNamesById[$idPob] ?? '') !== '' ? $branchNamesById[$idPob] : 'Pobočka ' . (string)$idPob;
                }
                $branchLabel = 'Pobočky: ' . implode(', ', $branchNames);
            }
        }

        $sql = "
            SELECT
                CASE
                    WHEN LOWER(COALESCE(cp.kod, '')) = 'wolt' THEN 'Wolt'
                    WHEN LOWER(COALESCE(cp.kod, '')) = 'bolt' THEN 'Bolt'
                    WHEN LOWER(COALESCE(cp.kod, '')) IN ('foodora', 'damejidlo') THEN 'Foodora'
                    WHEN LOWER(COALESCE(cp.kod, '')) IN ('manual', 'phone') THEN 'Ruční zadání'
                    WHEN LOWER(COALESCE(cp.kod, '')) = 'generic' THEN 'Vlastní web'
                    ELSE 'Ostatní'
                END AS kanal,
                COUNT(*) AS objednavky,
                SUM(COALESCE(c.cena_celk, 0)) AS trzba
            FROM objednavky_restia o
            LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
            LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
            LEFT JOIN cis_obj_stav st ON st.id_stav = o.id_stav
            WHERE o.restia_created_at >= ?
              AND o.restia_created_at < ?
              " . $branchFilterSql . "
              AND COALESCE(st.nazev, '') NOT IN ('canceled','rejected','expired','not_accepted','cancel_accepted')
            GROUP BY kanal
            ORDER BY trzba DESC
            LIMIT 8
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit Top report: ' . $conn->error);
        }
        $stmt->bind_param('ss', $fromSql, $toSql);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $row['objednavky'] = (int)$row['objednavky'];
            $row['trzba'] = (float)$row['trzba'];
            $totalOrders += $row['objednavky'];
            $totalSales += $row['trzba'];
            $maxSales = max($maxSales, $row['trzba']);
            $rows[] = $row;
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $stmt->close();
    } catch (Throwable $e) {
        echo '<section class="blok"><h2 class="blok_title">Top report</h2><p class="txt_cervena">Chyba Top reportu: ' . h($e->getMessage()) . '</p></section>';
        return;
    }
    ?>
    <section class="blok">
        <h2 class="blok_title">Top report</h2>
        <p class="provoz_prehled_meta"><?= h($periodLabel) ?></p>
        <?php if ($branchLabel !== ''): ?>
            <p class="provoz_prehled_meta"><?= h($branchLabel) ?></p>
        <?php endif; ?>
        <p class="provoz_top_report_heading">Souhrn tržeb podle kanálů</p>
        <?php if ($rows === []): ?>
            <p class="provoz_prehled_text">Nejsou dostupná data kanálů.</p>
        <?php else: ?>
            <div class="provoz_top_report_chart">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $channel = (string)$row['kanal'];
                    $sales = (float)$row['trzba'];
                    $share = $totalSales > 0 ? ($sales / $totalSales) * 100 : 0.0;
                    $width = $maxSales > 0 ? max(3.0, min(100.0, ($sales / $maxSales) * 100.0)) : 3.0;
                    $color = $channelColors[$channel] ?? $channelColors['Ostatní'];
                    ?>
                    <div class="provoz_top_report_row">
                        <span class="provoz_top_report_label"><?= h($channel) ?></span>
                        <span class="provoz_top_report_track" style="--provoz-top-report-width:<?= h(number_format($width, 2, '.', '')) ?>%;--provoz-top-report-color:<?= h($color) ?>;">
                            <span class="provoz_top_report_bar"></span>
                        </span>
                        <span class="provoz_top_report_share"><?= h(number_format($share, 1, ',', ' ')) ?> %</span>
                        <span class="provoz_top_report_value"><?= h(number_format($sales, 0, ',', ' ')) ?> Kč</span>
                    </div>
                <?php endforeach; ?>
                <div class="provoz_top_report_total">
                    <strong class="provoz_top_report_label">Celkem</strong>
                    <span class="provoz_top_report_total_space"></span>
                    <strong class="provoz_top_report_share"></strong>
                    <strong class="provoz_top_report_value"><?= h(number_format($totalSales, 0, ',', ' ')) ?> Kč</strong>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
})();
