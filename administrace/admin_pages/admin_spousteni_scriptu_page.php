<?php
declare(strict_types=1);

$adminScriptResult = $_SESSION['cb_admin_script_result'] ?? null;
$adminScriptResultType = is_array($adminScriptResult) ? (string)($adminScriptResult['script'] ?? 'hr') : '';
$adminHrLocal = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL');
$adminGoogleSourceStatus = cb_admin_google_reporty_stav_zdroje();
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
        <p class="admin_script_description">První kompletní naplnění HR z <code>data/google_data/HR.zip</code>. Nejprve zkontroluje podklady, teprve po potvrzení vyprázdní HR a provede celý import.</p>

        <?php if (is_array($adminScriptResult) && in_array($adminScriptResultType, ['hr_kompletni_preview', 'hr_kompletni_import'], true)): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= nl2br(h((string)($adminScriptResult['message'] ?? ''))) ?>
            </p>
        <?php endif; ?>

        <?php if ($adminHrLocal): ?>
            <p class="admin_script_result" data-admin-hr-import-progress aria-live="polite" hidden></p>
            <form class="admin_script_form admin_script_form--hr" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>" data-admin-hr-preview-form>
                <input type="hidden" name="cb_action" value="admin_hr_kompletni_preview">
                <button class="admin_script_button" type="submit" data-admin-hr-preview-button>Zkontrolovat podklady pro import HR</button>
            </form>

            <?php if (is_array($adminScriptResult) && $adminScriptResultType === 'hr_kompletni_preview' && !empty($adminScriptResult['success'])): ?>
                <?php $adminHrPreview = (array)($adminScriptResult['preview'] ?? []); ?>
                <div class="admin_script_preview_wrap">
                    <table class="admin_script_preview_table">
                        <tbody>
                            <tr><th>Formulář</th><td><?= h((string)($adminHrPreview['formular'] ?? '')) ?></td></tr>
                            <tr><th>Mzdy</th><td><?= h((string)($adminHrPreview['mzdy'] ?? '')) ?></td></tr>
                            <tr><th>Dokumenty</th><td><?= h((string)($adminHrPreview['dokumenty'] ?? '')) ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <form class="admin_script_form admin_script_form--hr" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
                    <input type="hidden" name="cb_action" value="admin_hr_kompletni_import">
                    <label class="admin_script_confirm">
                        <input type="checkbox" name="admin_hr_kompletni_confirm" value="1" required>
                        <span>Opravdu kompletně vyprázdnit HR, importovat USER → PERSON a následně doplnit všechna dostupná zaměstnanecká data, mzdy, sazby a dokumenty?</span>
                    </label>
                    <button class="admin_script_button" type="submit">Vyprázdnit HR a provést kompletní import</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p class="admin_script_server_notice">Kompletní naplnění HR je dostupné pouze v lokálním prostředí.</p>
        <?php endif; ?>
    </div>

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
