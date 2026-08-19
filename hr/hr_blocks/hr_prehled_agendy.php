<?php
/*
 * Ucel souboru: Vykresluje blok agend na strance HR prehled.
 * Pouziva data pripravena rodicovskou strankou a nevytvari PP ani hlavni layout.
 */
declare(strict_types=1);
?>
<section class="hr_prehled_grid">
    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Dokumenty</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=dokumenty')) ?>">Zobrazit</a>
        </div>
        <?php if ($dokumenty === []): ?>
            <p class="hr_empty_state">Zatím nejsou evidované žádné nové dokumenty.</p>
        <?php else: ?>
            <ul class="hr_activity_list">
                <?php foreach ($dokumenty as $dokument): ?>
                    <li class="hr_activity_item">
                        <span class="hr_dot hr_blue"></span>
                        <strong class="hr_activity_name"><?= h($dokument['osoba']) ?></strong>
                        <span><?= h($dokument['typ']) ?> · <?= h($dokument['nazev']) ?></span>
                        <time class="hr_activity_time"><?= h(hr_format_date((string)$dokument['zadano'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Lékařské prohlídky</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=prohlidky')) ?>">Zobrazit</a>
        </div>
        <?php if ($lekarskeProhlidky === []): ?>
            <p class="hr_empty_state">Evidence lékařských prohlídek zatím není napojená.</p>
        <?php else: ?>
            <ul class="hr_activity_list"></ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Školení</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=skoleni')) ?>">Zobrazit</a>
        </div>
        <?php if ($skoleni === []): ?>
            <p class="hr_empty_state">Evidence školení zatím není napojená.</p>
        <?php else: ?>
            <ul class="hr_activity_list"></ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Dovolené</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=dovolene')) ?>">Zobrazit</a>
        </div>
        <?php if ($dovolene === []): ?>
            <p class="hr_empty_state">Evidence dovolených zatím není napojená.</p>
        <?php else: ?>
            <ul class="hr_activity_list"></ul>
        <?php endif; ?>
    </article>
</section>
