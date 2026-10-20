<?php
// Souhrn tržeb podle kanálů za všechny povolené pobočky; chyby mají společný uživatelský výstup.
declare(strict_types=1);

require_once __DIR__ . '/../../common/db/db_user_pobocka.php';
require_once __DIR__ . '/../../common/lib/obdobi_stranky.php';

(static function (): void {
    // Časový filtr Top reportu je oddělen od Objednávek i Kontroly reportů.
    $topPeriod = cb_obdobi_stranky_model('provoz.top_report');
    $fromSql = $topPeriod['od'];
    $toSql = $topPeriod['do'];
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
        // Souhrn smí zahrnout jen povolené pobočky, i když uživatel nemá žádnou.
        $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
        $allowedBranchIds = array_values(array_filter(array_map('intval', array_column(cb_db_user_pobocky($conn, $idUser), 'id_pob')), static fn(int $id): bool => $id > 0));
        $branchFilterSql = $allowedBranchIds !== []
            ? ' AND o.id_pob IN (' . implode(',', $allowedBranchIds) . ')'
            : ' AND 1 = 0';

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
        $errorMessage = cb_chyba_uzivatel($e, [
            'module' => 'PROVOZ',
            'action' => 'Načtení Top reportu',
        ]);
        echo '<section class="blok"><h2 class="blok_title">Top report</h2><p class="txt_cervena">' . h($errorMessage) . '</p></section>';
        return;
    }
    ?>
    <section class="blok" data-cb-restia-needed="1">
        <!-- Období patří vpravo k titulku tohoto souhrnu. -->
        <div class="provoz_top_report_head">
            <h2 class="blok_title">Top report</h2>
            <?php $cbPeriodScope = 'provoz.top_report'; require __DIR__ . '/../../common/includes/vyber_obdobi.php'; ?>
        </div>
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
                        <span class="provoz_top_report_value"><?= h(cb_format('p', $sales)) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="provoz_top_report_total">
                    <strong class="provoz_top_report_label">Celkem</strong>
                    <span class="provoz_top_report_total_space"></span>
                    <strong class="provoz_top_report_share"></strong>
                    <strong class="provoz_top_report_value"><?= h(cb_format('p', $totalSales)) ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
})();
