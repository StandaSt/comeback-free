<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/denni_report_data.php';

(static function (): void {
    try {
        $conn = db();
        if (method_exists($conn, 'set_charset')) {
            $conn->set_charset('utf8mb4');
        }

        $data = cb_denni_report_prehled_data($conn);
        $missingReports = is_array($data['missingReports'] ?? null) ? $data['missingReports'] : [];
        $missingReportsMonth = is_array($data['missingReportsMonth'] ?? null) ? $data['missingReportsMonth'] : [];
        $missingReportsMonthText = $missingReportsMonth !== []
            ? implode(', ', array_map(static fn(array $row): string => (string)$row['nazev'] . ' ' . (string)((int)($row['missing_count'] ?? 0)) . 'x', $missingReportsMonth))
            : 'OK';
    } catch (Throwable $e) {
        echo '<section class="blok"><h2 class="blok_title">Nezadané denní reporty</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="blok blok_denni_report">
        <div class="provoz_nezadane_head">
            <h2 class="blok_title">Nezadané denní reporty</h2>
            <div class="provoz_nezadane_export" aria-label="Export nezadaných denních reportů">
                <span>Export měsíce:</span>
                <button type="button" class="provoz_nezadane_export_btn" data-nezadane-export-open="current">aktuální</button>
                <button type="button" class="provoz_nezadane_export_btn" data-nezadane-export-open="previous">minulý</button>
            </div>
        </div>
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
        <p class="provoz_prehled_text">&nbsp;</p>
        <p class="provoz_prehled_text txt_seda">Tento měsíc chybí reporty:</p>
        <p class="provoz_prehled_text txt_seda"><?= h($missingReportsMonthText) ?></p>

    </section>
    <?php
})();
