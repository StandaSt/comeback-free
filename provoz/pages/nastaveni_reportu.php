<?php
// Nastavení sazeb denního reportu; zobrazené částky používají společné formátování.
declare(strict_types=1);

require_once __DIR__ . '/../lib/report_promenne.php';
require_once __DIR__ . '/../lib/pobocka_provoz.php';

$flash = $_SESSION['cb_report_promenne_flash'] ?? null;
unset($_SESSION['cb_report_promenne_flash']);
$provozFlash = $_SESSION['cb_pobocka_provoz_flash'] ?? null;
unset($_SESSION['cb_pobocka_provoz_flash']);

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
    $loadError = cb_chyba_uzivatel($e, [
        'module' => 'PROVOZ',
        'action' => 'Načtení nastavení reportu',
    ]);
}

$currentWoltDrive = is_array($current) ? (float)($current['wolt_drive'] ?? 0) : null;
$currentWoltDriveLabel = $currentWoltDrive === null ? 'není nastaveno' : cb_format('p2', $currentWoltDrive);
$currentPhmSoukrome = is_array($current) ? (int)($current['phm_soukrome'] ?? 0) : null;
$currentPhmSoukromeLabel = $currentPhmSoukrome === null ? 'není nastaveno' : cb_format('p', $currentPhmSoukrome);
$token = cb_report_promenne_token();
$provozToken = cb_pobocka_provoz_token();
$settingsBranchId = max(0, (int)($_GET['zr_id_pob'] ?? $_POST['zr_id_pob'] ?? 0));
$settingsBranch = null;
$closedDays = [];
try {
    $settingsBranch = cb_pobocka_provoz_branch(db(), $settingsBranchId, cb_pobocka_provoz_user_id());
    $closedDays = cb_pobocka_provoz_closed_days(db());
} catch (Throwable $e) {
    $loadError = $loadError !== '' ? $loadError : cb_chyba_uzivatel($e, [
        'module' => 'PROVOZ',
        'action' => 'Načtení nastavení provozu',
    ]);
}
$settingsParams = ['m' => 'provoz', 'page' => 'nastaveni_reportu'];
if ($settingsBranchId > 0) {
    $settingsParams['zr_id_pob'] = $settingsBranchId;
}
$settingsActionUrl = cb_root_url('index.php') . '?' . http_build_query($settingsParams, '', '&', PHP_QUERY_RFC3986);
$closingTimeOptions = cb_pobocka_provoz_closing_time_options();
$monthLabels = [
    1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben', 5 => 'květen', 6 => 'červen',
    7 => 'červenec', 8 => 'srpen', 9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
];
$todayMonth = (int)date('n');
$todayDay = (int)date('j');
?>
<section class="provoz_prehled_block">
    <?php if (is_array($flash)): ?>
        <?php $flashClass = (string)($flash['typ'] ?? '') === 'ok' ? 'txt_zelena' : 'txt_cervena'; ?>
        <p class="<?= h($flashClass) ?>"><?= h((string)($flash['text'] ?? '')) ?></p>
    <?php endif; ?>

    <?php if (is_array($provozFlash)): ?>
        <?php $provozFlashClass = (string)($provozFlash['typ'] ?? '') === 'ok' ? 'txt_zelena' : 'txt_cervena'; ?>
        <p class="<?= h($provozFlashClass) ?>"><?= h((string)($provozFlash['text'] ?? '')) ?></p>
    <?php endif; ?>

    <?php if ($loadError !== ''): ?>
        <p class="txt_cervena">Chyba načtení nastavení reportu: <?= h($loadError) ?></p>
    <?php endif; ?>

    <div class="report_promenne_form">
        <form action="<?= h($settingsActionUrl) ?>" data-report-promenne-form="1">
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
                        <button type="button" class="head_task_btn" data-report-promenne-save="1">Uložit Wolt drive</button>
                    </div>
                </div>
            </div>
        </form>

        <form action="<?= h($settingsActionUrl) ?>" data-report-promenne-form="1">
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
                        <button type="button" class="head_task_btn" data-report-promenne-save="1">Uložit PHM soukromé</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if (is_array($settingsBranch)): ?>
            <form action="<?= h($settingsActionUrl) ?>" data-pobocka-provoz-form="1">
                <input type="hidden" name="token" value="<?= h($provozToken) ?>">
                <input type="hidden" name="provoz_action" value="save_hours">
                <input type="hidden" name="zr_id_pob" value="<?= h((string)$settingsBranchId) ?>">
                <div class="report_promenne_box">
                    <div class="report_promenne_box_title">Zavírací doba pobočky <?= h((string)($settingsBranch['nazev'] ?? '')) ?></div>
                    <div class="pobocka_provoz_hours_row">
                        <div class="pobocka_provoz_hours">
                            <?php foreach (cb_pobocka_provoz_weekdays() as $day): ?>
                                <?php
                                $dayKey = (string)$day['key'];
                                $currentTime = substr((string)($settingsBranch[$dayKey] ?? ''), 0, 5);
                                $currentTimeValid = in_array($currentTime, $closingTimeOptions, true);
                                ?>
                                <label>
                                    <span><?= h((string)$day['label']) ?></span>
                                    <select name="<?= h($dayKey) ?>" required>
                                        <?php if (!$currentTimeValid): ?>
                                            <option value="" selected>Upravte <?= h($currentTime) ?></option>
                                        <?php endif; ?>
                                        <?php foreach ($closingTimeOptions as $timeOption): ?>
                                            <option value="<?= h($timeOption) ?>"<?= $currentTimeValid && $timeOption === $currentTime ? ' selected' : '' ?>><?= h($timeOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="head_task_btn pobocka_provoz_hours_save" data-pobocka-provoz-save="1">Uložit zavírací dobu</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="report_promenne_box">
                <div class="report_promenne_box_title">Zavírací doba pobočky</div>
                <p class="txt_cervena">Pobočka není vybraná nebo k ní nemáte přístup. Otevřete nastavení z denního reportu vybrané pobočky.</p>
            </div>
        <?php endif; ?>

        <div class="report_promenne_box">
            <div class="report_promenne_box_title">Dny, kdy jsou všechny restaurace zavřené</div>
            <form action="<?= h($settingsActionUrl) ?>" class="pobocka_provoz_closed_add" data-pobocka-provoz-form="1">
                <input type="hidden" name="token" value="<?= h($provozToken) ?>">
                <input type="hidden" name="provoz_action" value="add_closed_date">
                <input type="hidden" name="zr_id_pob" value="<?= h((string)$settingsBranchId) ?>">
                <span>Každý rok</span>
                <label>den
                    <select name="den" required>
                        <?php for ($day = 1; $day <= 31; $day++): ?>
                            <option value="<?= h((string)$day) ?>"<?= $day === $todayDay ? ' selected' : '' ?>><?= h((string)$day) ?>.</option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label>měsíc
                    <select name="mesic" required>
                        <?php foreach ($monthLabels as $month => $monthLabel): ?>
                            <option value="<?= h((string)$month) ?>"<?= $month === $todayMonth ? ' selected' : '' ?>><?= h($monthLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" class="head_task_btn" data-pobocka-provoz-save="1">Přidat zavřený den</button>
            </form>
            <?php if ($closedDays === []): ?>
                <p class="txt_seda">Nejsou evidované žádné společné zavřené dny.</p>
            <?php else: ?>
                <div class="pobocka_provoz_closed_list">
                    <?php foreach ($closedDays as $closedDay): ?>
                        <?php
                        $closedMonth = (int)($closedDay['mesic'] ?? 0);
                        $closedDayNumber = (int)($closedDay['den'] ?? 0);
                        ?>
                        <form action="<?= h($settingsActionUrl) ?>" data-pobocka-provoz-form="1">
                            <input type="hidden" name="token" value="<?= h($provozToken) ?>">
                            <input type="hidden" name="provoz_action" value="remove_closed_date">
                            <input type="hidden" name="zr_id_pob" value="<?= h((string)$settingsBranchId) ?>">
                            <input type="hidden" name="mesic" value="<?= h((string)$closedMonth) ?>">
                            <input type="hidden" name="den" value="<?= h((string)$closedDayNumber) ?>">
                            <span><?= h(sprintf('%02d.%02d.', $closedDayNumber, $closedMonth)) ?></span>
                            <button type="button" class="head_task_btn" data-pobocka-provoz-save="1" data-confirm="Opravdu odstranit tento zavřený den?">Odebrat</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
