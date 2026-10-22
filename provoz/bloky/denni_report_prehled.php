<?php
// Prehled reportu rozlisuje serverovou uzaverku v 05:00 a stav lokalniho importu; export nabizi server.
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/denni_report_data.php';
require_once __DIR__ . '/../lib/nezadane_reporty_export_data.php';

// Nacte chybejici reporty a vykresli panel bez zasahu do jejich dat.
(static function (): void {
    $isLocal = ($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL';
    $exportAllowed = !$isLocal && cb_nezadane_reporty_export_ma_pravo();
    try {
        $conn = db();
        if (method_exists($conn, 'set_charset')) {
            $conn->set_charset('utf8mb4');
        }

        $data = cb_denni_report_prehled_data($conn);
        $submittedReports = (array)($data['submittedReports'] ?? []);
        $showSubmitted = !$isLocal && $submittedReports !== [];
        $missingReports = is_array($data['missingReports'] ?? null) ? $data['missingReports'] : [];
        $missingReportsMonth = is_array($data['missingReportsMonth'] ?? null) ? $data['missingReportsMonth'] : [];
        $missingReportsMonthText = implode(', ', array_map(
            static fn(array $row): string => (string)$row['nazev'] . ' ' . (string)((int)($row['missing_count'] ?? 0)) . 'x',
            $missingReportsMonth
        ));
    } catch (Throwable $e) {
        echo '<section class="blok"><h2 class="blok_title">Nezadané denní reporty</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="blok blok_denni_report">
        <div class="provoz_nezadane_head">
            <h2 class="blok_title"><?= $showSubmitted ? 'Dnes zadané reporty' : 'Nezadané denní reporty' ?></h2>
            <?php if (!$isLocal): ?>
            <div class="provoz_nezadane_export<?= $exportAllowed ? '' : ' cb_pravo_skryte' ?>" aria-label="Export nezadaných denních reportů"<?= $exportAllowed ? '' : ' aria-hidden="true" inert' ?>>
                <span>Export měsíce:</span>
                <button type="button" class="provoz_nezadane_export_btn" data-nezadane-export-open="current">aktuální</button>
                <button type="button" class="provoz_nezadane_export_btn" data-nezadane-export-open="previous">minulý</button>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($isLocal): ?>
            <p class="provoz_prehled_text txt_seda">Poslední import reportů: <?= h($data['lastImport'] instanceof DateTimeImmutable ? cb_format('dcs', $data['lastImport']) : 'není k dispozici') ?></p>
        <?php endif; ?>
        <?php if ($showSubmitted): ?>
            <p class="provoz_prehled_text txt_seda"><?= h((string)$data['submittedReportDate']) ?></p>
            <p class="provoz_prehled_text txt_seda"><?= h(implode(', ', array_column($submittedReports, 'nazev'))) ?></p>
            <?php if ($missingReports !== []): ?>
                <p class="provoz_prehled_text txt_seda">Nezadané denní reporty:</p>
            <?php endif; ?>
        <?php endif; ?>
        <div class="provoz_nezadane_table_scroll">
            <table class="provoz_prehled_mini_table">
                <tbody>
                    <?php foreach ($missingReports as $missingReport): ?>
                        <?php
                        $labelParts = preg_split('/\s+/', trim((string)($missingReport['label'] ?? '')), 2);
                        $weekday = (string)($labelParts[0] ?? '');
                        $dateLabel = (string)($labelParts[1] ?? '');
                        ?>
                        <tr>
                            <td class="provoz_prehled_mini_cell txt_seda"><?= h($weekday) ?></td>
                            <td class="provoz_prehled_mini_cell txt_seda"><?= h($dateLabel) ?></td>
                            <td class="provoz_prehled_mini_cell txt_seda"><?= h((string)($missingReport['branches_text'] ?? 'Žádné')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($data['monthCovered'])): ?>
            <?php // Bez pokryti aktualniho mesice nelze tvrdit, ze jsou vsechny jeho reporty zadane. ?>
        <?php elseif ($missingReportsMonth !== []): ?>
            <p class="provoz_prehled_text txt_seda">Tento měsíc chybí reporty:</p>
            <p class="provoz_prehled_text txt_seda"><?= h($missingReportsMonthText) ?></p>
        <?php else: ?>
            <p class="provoz_prehled_text txt_seda">Tento měsíc jsou zadány všechny reporty.</p>
        <?php endif; ?>

    </section>
    <?php
})();
