<?php
/*
 * Ucel souboru: Vykresluje blok rychlych odkazu na strance HR prehled.
 * Neprovadi DB logiku a nevytvari PP ani hlavni layout.
 */
declare(strict_types=1);
?>
<article class="hr_panel">
    <div class="hr_panel_header">
        <h2 class="hr_panel_title">Rychlé odkazy</h2>
    </div>
    <div class="hr_quick_links">
        <a class="hr_quick_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Seznam zaměstnanců <span>›</span></a>
        <a class="hr_quick_link" href="<?= h(cb_root_url('index.php?m=hr&page=pracovni_pomery')) ?>">Pracovní poměry <span>›</span></a>
        <a class="hr_quick_link" href="<?= h(cb_root_url('index.php?m=hr&page=dokumenty')) ?>">Dokumenty <span>›</span></a>
    </div>
</article>
