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
        <dl class="hr_document_summary">
            <div><dt>Aktivních zaměstnanců</dt><dd><?= h((string)$dokumenty['aktivnich_zamestnancu']) ?></dd></div>
            <div><dt>Pracovní smlouva <small>ano / ne</small></dt><dd><span><?= h((string)$dokumenty['se_smlouvou']) ?></span> / <span class="hr_document_summary_no"><?= h((string)$dokumenty['chybi_smlouva']) ?></span></dd></div>
            <div><dt>Osobní doklad <small>ano / ne</small></dt><dd><span><?= h((string)$dokumenty['s_osobnim_dokladem']) ?></span> / <span class="hr_document_summary_no"><?= h((string)$dokumenty['chybi_osobni_doklad']) ?></span></dd></div>
            <div><dt>Kartička zdrav. pojišťovny <small>ano / ne</small></dt><dd><span><?= h((string)$dokumenty['s_kartickou_pojistovny']) ?></span> / <span class="hr_document_summary_no"><?= h((string)$dokumenty['chybi_karticka_pojistovny']) ?></span></dd></div>
        </dl>
        <p class="hr_document_summary_note">Celkem <?= h((string)$dokumenty['dokumentu_celkem']) ?> dokumentů pro <?= h((string)$dokumenty['osob_s_dokumenty']) ?> osob; z toho <?= h((string)$dokumenty['dokumentu_mimo_aktivni']) ?> dokumentů u <?= h((string)$dokumenty['osob_mimo_aktivni']) ?> osob bez aktuálního vztahu.</p>
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
