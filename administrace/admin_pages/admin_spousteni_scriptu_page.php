<?php
declare(strict_types=1);

$adminScriptResult = $_SESSION['cb_admin_script_result'] ?? null;
$adminScriptResultType = is_array($adminScriptResult) ? (string)($adminScriptResult['script'] ?? 'hr') : '';
$adminHrLocal = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL');
if (isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
    unset($_SESSION['cb_admin_script_result']);
}
?>
<div class="admin_script_run">
    <div class="blok admin_script_card">
        <p class="admin_script_description"><?= $adminHrLocal
            ? 'Reset testovacích HR dat s volitelným importem uživatelů.'
            : 'Jednorázový kompletní reset HR a import uživatelů na serveru.' ?></p>

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
                <p class="admin_script_server_notice">Spuštění je možné pouze tehdy, když je tabulka hr_person prázdná. Po prvním importu už skript další spuštění nepovolí.</p>
            <?php endif; ?>

            <label class="admin_script_confirm">
                <input type="checkbox" name="admin_hr_import_confirm" value="1" required>
                <span>Rozumím, že zvolená testovací HR data budou nevratně odstraněna.</span>
            </label>
            <button class="admin_script_button" type="submit"><?= $adminHrLocal ? 'Spustit zvolený reset' : 'Spustit jednorázový import HR' ?></button>
        </form>
    </div>

    <div class="blok admin_script_card">
        <p class="admin_script_description">Doplnění chybějících naplánovaných směn.</p>

        <?php if (is_array($adminScriptResult) && $adminScriptResultType === 'smeny_plan'): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= h((string)($adminScriptResult['message'] ?? '')) ?>
            </p>
        <?php endif; ?>

        <form class="admin_script_form" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
            <input type="hidden" name="cb_action" value="admin_smeny_plan_doplnit">
            <label class="admin_script_confirm">
                <input type="checkbox" name="admin_smeny_plan_confirm" value="1" required>
                <span>Rozumím, že budou z API Směn doplněny pouze chybějící týdny naplánovaných směn.</span>
            </label>
            <button class="admin_script_button" type="submit">Doplnit naplánované směny</button>
        </form>
    </div>
</div>
