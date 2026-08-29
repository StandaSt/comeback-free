<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/report_promenne.php';

$flash = $_SESSION['cb_report_promenne_flash'] ?? null;
unset($_SESSION['cb_report_promenne_flash']);

if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(CB_REPORT_PROMENNE_PRAVO)) {
    echo '<section class="provoz_prehled_block"><p class="txt_cervena">Nemáte právo zobrazit nastavení reportu.</p></section>';
    return;
}

$today = date('Y-m-d');
$current = null;
$loadError = '';
try {
    $current = cb_report_promenne_for_date(db(), $today);
    if (!is_array($current)) {
        $current = cb_report_promenne_active(db());
    }
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$currentWoltDrive = is_array($current) ? (float)($current['wolt_drive'] ?? 0) : null;
$currentWoltDriveLabel = $currentWoltDrive === null ? 'není nastaveno' : number_format($currentWoltDrive, 2, ',', ' ') . ' Kč';
$currentPhmSoukrome = is_array($current) ? (int)($current['phm_soukrome'] ?? 0) : null;
$currentPhmSoukromeLabel = $currentPhmSoukrome === null ? 'není nastaveno' : number_format($currentPhmSoukrome, 0, ',', ' ') . ' Kč';
$token = cb_report_promenne_token();
?>
<section class="provoz_prehled_block">
    <?php if (is_array($flash)): ?>
        <?php $flashClass = (string)($flash['typ'] ?? '') === 'ok' ? 'txt_zelena' : 'txt_cervena'; ?>
        <p class="<?= h($flashClass) ?>"><?= h((string)($flash['text'] ?? '')) ?></p>
    <?php endif; ?>

    <?php if ($loadError !== ''): ?>
        <p class="txt_cervena">Chyba načtení proměnných reportu: <?= h($loadError) ?></p>
    <?php endif; ?>

    <div class="report_promenne_form">
        <form action="<?= h(cb_root_url('index.php?m=provoz&page=nastaveni_reportu')) ?>" data-report-promenne-form="1">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="promenna" value="wolt_drive">
            <div class="report_promenne_box">
                <div class="report_promenne_box_title">Wolt drive - částka za objednávku</div>
                <div class="report_promenne_row report_promenne_values">
                    <div>Aktuální hodnota <strong><?= h($currentWoltDriveLabel) ?></strong></div>
                    <label>Nová hodnota <input type="text" name="wolt_drive" inputmode="decimal" required> Kč</label>
                </div>
                <div class="report_promenne_row report_promenne_validity">
                    <strong>Změna bude platná</strong>
                    <label><input type="radio" name="plati_mode" value="hned" checked> Ihned</label>
                    <label><input type="radio" name="plati_mode" value="datum"> Platná od <input type="date" name="plati_od" value="<?= h($today) ?>"></label>
                    <div class="report_promenne_actions">
                        <button type="button" data-report-promenne-save="1">Uložit Wolt drive</button>
                    </div>
                </div>
            </div>
        </form>

        <form action="<?= h(cb_root_url('index.php?m=provoz&page=nastaveni_reportu')) ?>" data-report-promenne-form="1">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="promenna" value="phm_soukrome">
            <div class="report_promenne_box">
                <div class="report_promenne_box_title">PHM soukromé - částka za objednávku</div>
                <div class="report_promenne_row report_promenne_values">
                    <div>Aktuální hodnota <strong><?= h($currentPhmSoukromeLabel) ?></strong></div>
                    <label>Nová hodnota <input type="text" name="phm_soukrome" inputmode="numeric" required> Kč</label>
                </div>
                <div class="report_promenne_row report_promenne_validity">
                    <strong>Změna bude platná</strong>
                    <label><input type="radio" name="plati_mode" value="hned" checked> Ihned</label>
                    <label><input type="radio" name="plati_mode" value="datum"> Platná od <input type="date" name="plati_od" value="<?= h($today) ?>"></label>
                    <div class="report_promenne_actions">
                        <button type="button" data-report-promenne-save="1">Uložit PHM soukromé</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
