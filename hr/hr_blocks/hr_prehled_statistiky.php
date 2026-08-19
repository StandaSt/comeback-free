<?php
/*
 * Ucel souboru: Vykresluje blok statistik na strance HR prehled.
 * Pouziva data pripravena rodicovskou strankou a nevytvari PP ani hlavni layout.
 */
declare(strict_types=1);
?>
<section class="hr_stats_grid">
    <a class="hr_stat_box hr_accent_blue" href="<?= h(cb_root_url('index.php?m=hr&page=nabor')) ?>" aria-label="Nábor">
        <div class="hr_stat_icon">N</div>
        <div>
            <span class="hr_stat_label">Nábor</span>
            <strong class="hr_stat_value"><?= h($nabor['novy']) ?> / <?= h($nabor['v_procesu']) ?></strong>
            <small class="hr_stat_note">noví / v procesu</small>
        </div>
    </a>

    <a class="hr_stat_box hr_accent_green" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>" aria-label="Zaměstnanci">
        <div class="hr_stat_icon">Z</div>
        <div>
            <span class="hr_stat_label">Zaměstnanci</span>
            <strong class="hr_stat_value"><?= h($zamestnanci['HPP']) ?> / <?= h($zamestnanci['DPC']) ?> / <?= h($zamestnanci['DPP']) ?></strong>
            <small class="hr_stat_note">HPP / DPČ / DPP</small>
        </div>
    </a>

    <a class="hr_stat_box hr_accent_orange" href="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" aria-label="Požadavky">
        <div class="hr_stat_icon">P</div>
        <div>
            <span class="hr_stat_label">Požadavky</span>
            <strong class="hr_stat_value"><?= h($pozadavky['celkem']) ?> / <?= h($pozadavky['instor']) ?> / <?= h($pozadavky['kuryr']) ?></strong>
            <small class="hr_stat_note">celkem / instor / kurýr</small>
        </div>
    </a>

    <article class="hr_stat_box hr_accent_red">
        <div class="hr_stat_icon">!</div>
        <div>
            <span class="hr_stat_label">K řešení</span>
            <strong class="hr_stat_value"><?= h($kReseni['koncici_smlouvy']) ?> / <?= h($kReseni['zdravotni_prohlidky']) ?> / <?= h($kReseni['bozp']) ?></strong>
            <small class="hr_stat_note">smlouvy / prohlídky / BOZP</small>
        </div>
    </article>
</section>
