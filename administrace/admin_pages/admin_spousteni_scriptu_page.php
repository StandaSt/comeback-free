<?php
declare(strict_types=1);

$adminScriptResult = $_SESSION['cb_admin_script_result'] ?? null;
$adminScriptResultType = is_array($adminScriptResult) ? (string)($adminScriptResult['script'] ?? 'hr') : '';
$adminHrLocal = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL');
$adminHrServerCanRun = true;
$adminHrServerStatus = '';
$adminHrServerButtonLabel = 'Spustit jednorázový import HR';
if (!$adminHrLocal) {
    try {
        $adminHrServerResult = db()->query('SELECT 1 FROM hr_person LIMIT 1');
        if (!$adminHrServerResult instanceof mysqli_result) {
            throw new RuntimeException('Kontrola tabulky hr_person selhala.');
        }
        $adminHrServerCanRun = $adminHrServerResult->fetch_row() === null;
        $adminHrServerResult->free();
        $adminHrServerStatus = $adminHrServerCanRun
            ? 'Tabulka hr_person je prázdná - import lze spustit.'
            : 'Tabulka hr_person obsahuje data. Tento script nelze spustit.';
        if (!$adminHrServerCanRun) {
            $adminHrServerButtonLabel = 'Script nelze spustit, hr_person obsahuje data';
        }
    } catch (Throwable) {
        $adminHrServerCanRun = false;
        $adminHrServerStatus = 'Stav tabulky hr_person se nepodařilo ověřit. Tento script nelze spustit.';
        $adminHrServerButtonLabel = 'Script nelze spustit, stav hr_person není ověřen';
    }
}
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
        <p class="admin_script_description"><?= $adminHrLocal
            ? 'Reset testovacích HR dat s volitelným importem uživatelů.'
            : 'Jednorázový kompletní reset HR a import uživatelů na serveru.' ?></p>

        <?php if (!$adminHrLocal): ?>
            <p class="admin_script_server_notice"><strong>Toto je verze pro server!</strong></p>
        <?php endif; ?>

        <?php if (is_array($adminScriptResult) && $adminScriptResultType === 'hr'): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= h((string)($adminScriptResult['message'] ?? '')) ?>
            </p>
        <?php endif; ?>

        <form class="admin_script_form admin_script_form--hr" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
            <input type="hidden" name="cb_action" value="admin_hr_import_user">

            <?php if ($adminHrLocal): ?>
                <fieldset class="admin_script_options">
                    <legend>Rozsah resetu</legend>
                    <label class="admin_script_choice"><input type="radio" name="admin_hr_reset_scope" value="all" required> Kompletní reset VD, ND, uchazečů a zaměstnanců</label>
                    <label class="admin_script_choice"><input type="radio" name="admin_hr_reset_scope" value="vd"> Pouze VD a uchazeči</label>
                    <label class="admin_script_choice"><input type="radio" name="admin_hr_reset_scope" value="nd_employees"> Pouze ND a zaměstnanci</label>
                </fieldset>
                <label class="admin_script_choice">
                    <input type="checkbox" name="admin_hr_import_users" value="1">
                    Po resetu importovat chybějící uživatele do HR
                </label>
            <?php else: ?>
                <p class="admin_script_server_notice"><?= h($adminHrServerStatus) ?></p>
            <?php endif; ?>

            <?php if ($adminHrLocal || $adminHrServerCanRun): ?>
                <label class="admin_script_confirm">
                    <input type="checkbox" name="admin_hr_import_confirm" value="1" required>
                    <span>Rozumím, že zvolená testovací HR data budou nevratně odstraněna.</span>
                </label>
            <?php endif; ?>
            <button class="admin_script_button" type="submit"<?= !$adminHrLocal && !$adminHrServerCanRun ? ' disabled' : '' ?>><?= $adminHrLocal
                ? 'Spustit zvolený reset'
                : $adminHrServerButtonLabel ?></button>
        </form>
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
</div>
