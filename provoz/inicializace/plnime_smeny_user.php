<?php
declare(strict_types=1);

/*
 * Původní import uživatelů, rolí, poboček a slotů ze Směn je zrušen.
 * Směny jsou zdrojem pouze pro samostatný import naplánovaných směn.
 */

if (empty($GLOBALS['cb_smeny_user_cron'])) {
    ?>
    <section class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
        <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Import uživatelů ze Směn je zrušen</h2>
        <p class="card_text txt_seda">Uživatele, role, pobočky a pozice se spravují pouze v Comeback IS a HR.</p>
    </section>
    <?php
}
