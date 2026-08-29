<?php
declare(strict_types=1);

/* Potvrzeni vypnuti hlidani jednoho prava z cis_prava. */
?>
<dialog class="admin_confirm_dialog" data-admin-pravo-aktivni-modal>
    <div class="admin_confirm_dialog_content">
        <h2>Vypnout hlídání práva?</h2>
        <p data-admin-pravo-aktivni-text></p>
        <div class="admin_confirm_dialog_actions">
            <button class="admin_confirm_dialog_danger" type="button" data-admin-pravo-aktivni-confirm>Vypnout</button>
            <button type="button" data-admin-pravo-aktivni-cancel>Nechat aktivní</button>
        </div>
    </div>
</dialog>
