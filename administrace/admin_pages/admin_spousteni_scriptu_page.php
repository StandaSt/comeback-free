<?php
declare(strict_types=1);

$adminScriptResult = $_SESSION['cb_admin_script_result'] ?? null;
if (isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
    unset($_SESSION['cb_admin_script_result']);
}
?>
<div class="admin_script_run">
    <div class="blok admin_script_card">
        <p class="admin_script_description">Odstranění zaměstnanců z hr tabulek a nový import z user do hr_person.</p>

        <?php if (is_array($adminScriptResult)): ?>
            <p class="admin_script_result<?= empty($adminScriptResult['success']) ? ' is-error' : '' ?>">
                <?= h((string)($adminScriptResult['message'] ?? '')) ?>
            </p>
        <?php endif; ?>

        <form class="admin_script_form" method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')) ?>">
            <input type="hidden" name="cb_action" value="admin_hr_import_user">
            <label class="admin_script_confirm">
                <input type="checkbox" name="admin_hr_import_confirm" value="1" required>
                <span>Rozumím, že budou odstraněna testovací personální data HR a nahrazena novým importem.</span>
            </label>
            <button class="admin_script_button" type="submit">Spustit import zaměstnanců do HR</button>
        </form>
    </div>
</div>
