<?php
/* Účel: Okno ručně zadaných změn IS; aktuální obsah čte z DB až při otevření. */
declare(strict_types=1);
if (empty($_SESSION['login_ok'])) return;
?>
<dialog id="cb-verze-dialog" class="cb_verze_dialog" aria-labelledby="cb-verze-title" data-verze-url="<?= h(cb_root_url('index.php?cb_verze=seznam')) ?>">
    <div class="cb_verze_head">
        <h2 id="cb-verze-title">Verze a novinky</h2>
        <button type="button" class="cb_verze_close" data-cb-verze-close aria-label="Zavřít přehled verzí" autofocus>×</button>
    </div>
    <div class="cb_verze_content" data-verze-content aria-live="polite"></div>
    <div class="cb_verze_more"><button type="button" class="head_task_btn" data-verze-more hidden>Starší změny</button></div>
</dialog>
