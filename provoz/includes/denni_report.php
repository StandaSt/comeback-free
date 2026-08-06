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

        $data = cb_denni_report_prepare_data($conn, 'mini');
        $missingReports = is_array($data['miniMissingReports'] ?? null) ? $data['miniMissingReports'] : [];
    } catch (Throwable $e) {
        echo '<section class="prehled_block"><h2>Denní report</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="prehled_block prehled_block--denni-report">
        <h2>Denní report</h2>
        <p class="card_mini_text txt_cervena"><span class="text_tucny">Nezadané reporty</span></p>
        <table class="card_mini_table">
            <tbody>
                <?php foreach ($missingReports as $missingReport): ?>
                    <?php
                    $labelParts = preg_split('/\s+/', trim((string)($missingReport['label'] ?? '')), 2);
                    $weekday = (string)($labelParts[0] ?? '');
                    $dateLabel = (string)($labelParts[1] ?? '');
                    ?>
                    <tr>
                        <td class="txt_seda"><?= h($weekday) ?></td>
                        <td class="txt_seda"><?= h($dateLabel) ?></td>
                        <td class="txt_seda"><?= h((string)($missingReport['branches_text'] ?? 'Žádné')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="card_mini_text">&nbsp;</p>
        <p class="card_mini_text txt_cervena">Vypisujte prosím i tento report, je třeba odladit případné chyby. Díky</p>
    </section>
    <?php
})();
