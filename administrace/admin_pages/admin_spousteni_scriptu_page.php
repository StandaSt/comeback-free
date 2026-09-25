<?php
declare(strict_types=1);

$adminScriptResult = $_SESSION['cb_admin_script_result'] ?? null;
$adminScriptResultType = is_array($adminScriptResult) ? (string)($adminScriptResult['script'] ?? '') : '';
$adminGoogleSourceStatus = cb_admin_google_reporty_stav_zdroje();
$adminGoogleZipFiles = cb_admin_google_reporty_najdi_zipy(dirname(__DIR__, 3) . '/data/google_data');
$adminGoogleNewestZipName = (string)($adminGoogleZipFiles[0]['name'] ?? '');
$adminGoogleHistoryDates = cb_admin_google_historie_vychozi_data(db());
if ($adminScriptResultType === 'google_historie' && !empty($adminScriptResult['success']) && is_array($adminScriptResult['data'] ?? null)) {
    $adminGoogleHistoryDates = $adminScriptResult['data'];
}
$adminGoogleDateCz = static function (string $value): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value
        ? $date->format('j. n. Y')
        : $value;
};
if (isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
    unset($_SESSION['cb_admin_script_result']);
}
?>
<div class="admin_script_run">
    <div class="blok admin_script_card">
        <p class="admin_script_description"><?= h($adminGoogleSourceStatus) ?></p>

        <?php if (is_array($adminScriptResult) && $adminScriptResultType === 'google_reporty'): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= h((string)($adminScriptResult['message'] ?? '')) ?>
            </p>
        <?php endif; ?>

        <?php if (is_array($adminScriptResult) && $adminScriptResultType === 'google_reporty_preview'): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= h((string)($adminScriptResult['message'] ?? '')) ?>
            </p>
            <?php if (!empty($adminScriptResult['success'])): ?>
                <?php $adminGooglePreviewBranches = $adminScriptResult['branches'] ?? []; ?>
                <?php if (is_array($adminGooglePreviewBranches) && $adminGooglePreviewBranches !== []): ?>
                    <div class="admin_script_preview_wrap">
                        <table class="admin_script_preview_table">
                            <thead>
                                <tr>
                                    <th>Pobočka</th>
                                    <th>Data reportů</th>
                                    <th>Zdroj</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($adminGooglePreviewBranches as $adminGooglePreviewBranch): ?>
                                <tr>
                                    <td><?= h((string)($adminGooglePreviewBranch['name'] ?? '')) ?></td>
                                    <td><?= h($adminGoogleDateCz((string)($adminGooglePreviewBranch['from'] ?? ''))) ?> až <?= h($adminGoogleDateCz((string)($adminGooglePreviewBranch['until'] ?? ''))) ?></td>
                                    <td><?= h(implode(', ', (array)($adminGooglePreviewBranch['workbooks'] ?? []))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="admin_script_result">Není co importovat.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <form class="admin_script_form" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
            <input type="hidden" name="cb_action" value="admin_google_reporty_preview">
            <button class="admin_script_button" type="submit">Ukaž co se bude importovat</button>
        </form>

        <form class="admin_script_form" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
            <input type="hidden" name="cb_action" value="admin_google_reporty_import">
            <label class="admin_script_confirm">
                <input type="checkbox" name="admin_google_reporty_confirm" value="1" required>
                <span>Rozumím, že se použije nalezený ZIP a následně se importují chybějící reporty do databáze.</span>
            </label>
            <button class="admin_script_button" type="submit">Načíst reporty Google</button>
        </form>
    </div>

    <div class="blok admin_script_card">
        <p class="admin_script_description">Převod Google reportů do společné historie IS. Zvolte poslední den zvlášť pro restaurace a pro Výrobu. Platné reporty z IS mají přednost a zůstanou beze změny.</p>
        <p>Zdroj tohoto převodu je tabulka <code>reporty</code>, nikoli přímo ZIP. Nejnovější dostupný soubor: <strong><?= $adminGoogleNewestZipName !== '' ? h($adminGoogleNewestZipName) : 'nenalezen' ?></strong>. Pokud chcete převést jeho data, nejprve použijte horní „Načíst reporty Google“. Při přípravě nového ZIPu se po ověření odstraní starší ZIPy.</p>
        <p>Převod běží po pobočkách. Po spuštění se níže zobrazí právě zpracovávaná pobočka a dokončené kroky. Nechte stránku otevřenou až do výsledku.</p>

        <form class="admin_script_form admin_script_form--history-preview" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
            <input type="hidden" name="cb_action" value="admin_google_historie_preview">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
            <label class="admin_script_date_field">Restaurace do včetně
                <input type="date" name="google_historie_do_restaurace" value="<?= h((string)$adminGoogleHistoryDates['restaurace']) ?>" required>
            </label>
            <label class="admin_script_date_field">Výroba do včetně
                <input type="date" name="google_historie_do_vyroba" value="<?= h((string)$adminGoogleHistoryDates['vyroba']) ?>" required>
            </label>
            <button class="admin_script_button" type="submit">Ukázat reporty k převodu</button>
        </form>

        <?php if (is_array($adminScriptResult) && $adminScriptResultType === 'google_historie'): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= h((string)($adminScriptResult['message'] ?? '')) ?>
            </p>
            <?php $adminHistoryPreview = !empty($adminScriptResult['success']) ? (array)($adminScriptResult['preview'] ?? []) : []; ?>
            <?php if (!empty($adminHistoryPreview['branches'])): ?>
                <div class="admin_script_preview_wrap">
                    <table class="admin_script_preview_table">
                        <thead><tr><th>Pobočka</th><th>Počet reportů</th><th>Období</th></tr></thead>
                        <tbody>
                            <?php foreach ($adminHistoryPreview['branches'] as $adminHistoryBranch): ?>
                                <tr>
                                    <td><?= h((string)$adminHistoryBranch['nazev']) ?></td>
                                    <td><?= h((string)$adminHistoryBranch['pocet']) ?></td>
                                    <td><?= h($adminGoogleDateCz((string)$adminHistoryBranch['od'])) ?> až <?= h($adminGoogleDateCz((string)$adminHistoryBranch['do'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form class="admin_script_form" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>" data-admin-google-historie-form>
                    <input type="hidden" name="cb_action" value="admin_google_historie_import">
                    <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
                    <input type="hidden" name="google_historie_do_restaurace" value="<?= h((string)$adminGoogleHistoryDates['restaurace']) ?>">
                    <input type="hidden" name="google_historie_do_vyroba" value="<?= h((string)$adminGoogleHistoryDates['vyroba']) ?>">
                    <label class="admin_script_confirm">
                        <input type="checkbox" name="admin_google_historie_confirm" value="1" required>
                        <span>Rozumím, že převedu uvedené reporty do reporty_is podle zvolených koncových dat. Sazby se doplní později z HR.</span>
                    </label>
                    <button class="admin_script_button" type="submit" data-admin-google-historie-button>Převést Google reporty do IS</button>
                </form>
                <div class="admin_script_progress" data-admin-google-historie-progress hidden>
                    <p class="admin_script_result" data-admin-google-historie-status role="status" aria-live="polite"></p>
                    <progress data-admin-google-historie-bar value="0" max="1"></progress>
                    <ol data-admin-google-historie-branches></ol>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <div class="blok admin_script_card">
        <p class="admin_script_description">Načte aktuální katalog všech provozních poboček z Restie a zachová předchozí verze položek a cen.</p>

        <?php $adminRestiaHasResult = is_array($adminScriptResult) && $adminScriptResultType === 'restia_katalog'; ?>
        <p
            class="admin_script_result<?= $adminRestiaHasResult && empty($adminScriptResult['success']) ? ' is-error' : '' ?>"
            data-admin-restia-katalog-prubeh
            aria-live="polite"
            <?= $adminRestiaHasResult ? '' : 'hidden' ?>
        ><?= $adminRestiaHasResult ? h((string)($adminScriptResult['message'] ?? '')) : '' ?></p>

        <form class="admin_script_form" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>" data-admin-restia-katalog-form>
            <input type="hidden" name="cb_action" value="admin_restia_katalog">
            <label class="admin_script_confirm">
                <input type="checkbox" name="admin_restia_katalog_confirm" value="1" required>
                <span>Načíst katalog ze všech provozních poboček a uložit případné změny jako nové verze.</span>
            </label>
            <button class="admin_script_button" type="submit" data-admin-restia-katalog-button>Načtení položek z Restie</button>
        </form>
    </div>
</div>
