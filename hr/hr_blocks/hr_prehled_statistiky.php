<?php
/*
 * Ucel souboru: Vykresluje blok statistik na strance HR prehled.
 * Pouziva data pripravena rodicovskou strankou a nevytvari PP ani hlavni layout.
 */
declare(strict_types=1);
?>
<section class="hr_stats_grid">
    <a class="hr_stat_box hr_accent_blue" href="<?= h(cb_root_url('index.php?m=hr&page=nabor')) ?>" aria-label="Nábor">
        <span class="hr_stat_label">Nábor</span>
        <div class="hr_stat_metrics">
            <div class="hr_stat_icon">N</div>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($nabor['novy']) ?></strong><small>Nové</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($nabor['pohovor']) ?></strong><small>Pohovor</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($nabor['nastup']) ?></strong><small>Nástup</small></span>
        </div>
    </a>

    <a class="hr_stat_box hr_accent_green" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>" aria-label="Zaměstnanci">
        <span class="hr_stat_label">Zaměstnanci</span>
        <div class="hr_stat_metrics">
            <div class="hr_stat_icon">Z</div>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($zamestnanci['HPP']) ?></strong><small>HPP</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($zamestnanci['DPC']) ?></strong><small>DPČ</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($zamestnanci['DPP']) ?></strong><small>DPP</small></span>
        </div>
    </a>

    <a class="hr_stat_box hr_accent_orange" href="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" aria-label="Požadavky">
        <span class="hr_stat_label">Požadavky</span>
        <div class="hr_stat_metrics">
            <div class="hr_stat_icon">P</div>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($pozadavky['celkem']) ?></strong><small>Celkem</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($pozadavky['instor']) ?></strong><small>Instor</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($pozadavky['kuryr']) ?></strong><small>Kurýr</small></span>
        </div>
    </a>

    <article class="hr_stat_box hr_accent_red">
        <span class="hr_stat_label">K řešení</span>
        <div class="hr_stat_metrics">
            <div class="hr_stat_icon">!</div>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($kReseni['koncici_smlouvy']) ?></strong><small>Smlouvy</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($kReseni['zdravotni_prohlidky']) ?></strong><small>Prohlídky</small></span>
            <span class="hr_stat_metric"><strong class="hr_stat_value"><?= h($kReseni['bozp']) ?></strong><small>BOZP</small></span>
        </div>
    </article>
</section>
