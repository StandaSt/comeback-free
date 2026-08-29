<?php
// modaly/modal_nezadane_reporty_export.php * Vyber prijemce PDF s nezadanymi reporty
declare(strict_types=1);

$nezadaneExportRecipients = is_array($nezadaneExportRecipients ?? null) ? $nezadaneExportRecipients : [];
$nezadaneExportCsrf = (string)($nezadaneExportCsrf ?? '');
$nezadaneExportUrl = (string)($nezadaneExportUrl ?? '');
?>
<div class="provoz_nezadane_modal is-hidden" data-nezadane-export-modal data-endpoint="<?= h($nezadaneExportUrl) ?>" data-csrf="<?= h($nezadaneExportCsrf) ?>" aria-hidden="true">
    <div class="provoz_nezadane_dialog" role="dialog" aria-modal="true" aria-labelledby="nezadaneExportTitle">
        <h3 id="nezadaneExportTitle" class="provoz_nezadane_dialog_title">Odeslat nezadané reporty</h3>
        <p class="provoz_nezadane_dialog_period" data-nezadane-export-period></p>
        <label class="provoz_nezadane_dialog_label" for="nezadaneExportRecipient">E-mail příjemce</label>
        <select id="nezadaneExportRecipient" class="provoz_nezadane_dialog_select" data-nezadane-export-recipient>
            <option value="">Vyberte e-mail</option>
            <?php foreach ($nezadaneExportRecipients as $recipient): ?>
                <option value="<?= (int)($recipient['id_user'] ?? 0) ?>"><?= h((string)($recipient['email'] ?? '')) ?><?= trim((string)($recipient['name'] ?? '')) !== '' ? ' — ' . h((string)$recipient['name']) : '' ?></option>
            <?php endforeach; ?>
        </select>
        <p class="provoz_nezadane_dialog_status is-hidden" data-nezadane-export-message aria-live="polite"></p>
        <div class="provoz_nezadane_sent is-hidden" data-nezadane-export-sent aria-live="polite"></div>
        <div class="provoz_nezadane_dialog_actions">
            <button type="button" class="provoz_nezadane_dialog_btn provoz_nezadane_dialog_btn_back" data-nezadane-export-close>Zpět</button>
            <button type="button" class="provoz_nezadane_dialog_btn" data-nezadane-export-send>Odeslat PDF</button>
        </div>
    </div>
</div>
<?php
// modaly/modal_nezadane_reporty_export.php * Konec souboru
