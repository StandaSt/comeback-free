<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/report_promenne.php';

$flash = $_SESSION['cb_report_promenne_flash'] ?? null;
unset($_SESSION['cb_report_promenne_flash']);

if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(CB_REPORT_PROMENNE_PRAVO)) {
    echo '<section class="provoz_prehled_block"><p class="txt_cervena">Nemáte právo zobrazit nastavení reportu.</p></section>';
    return;
}

$active = null;
$loadError = '';
try {
    $active = cb_report_promenne_active(db());
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$currentValue = is_array($active) ? (float)($active['wolt_drive'] ?? 0) : null;
$currentLabel = $currentValue === null ? 'není nastaveno' : number_format($currentValue, 2, ',', ' ') . ' Kč';
$today = date('Y-m-d');
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

    <form action="<?= h(cb_root_url('index.php?m=provoz&page=nastaveni_reportu')) ?>" class="report_promenne_form" data-report-promenne-form="1">
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <div class="report_promenne_box">
            <div class="report_promenne_box_title">Změny budou platné</div>
            <label class="report_promenne_row"><input type="radio" name="plati_mode" value="hned" checked> Ihned</label>
            <label class="report_promenne_row"><input type="radio" name="plati_mode" value="datum"> Platné od <input type="date" name="plati_od" value="<?= h($today) ?>"></label>
        </div>

        <div class="report_promenne_box">
            <div class="report_promenne_box_title">Wolt drive - částka za objednávku</div>
            <div class="report_promenne_row">Aktuální hodnota <strong><?= h($currentLabel) ?></strong></div>
            <label class="report_promenne_row">Nová hodnota <input type="text" name="wolt_drive" inputmode="decimal" required> Kč</label>
            <div class="report_promenne_actions">
                <button type="button" data-report-promenne-save="1">Uložit</button>
            </div>
        </div>
    </form>
</section>
