<?php
/*
 * Ucel souboru: Vykresluje blok poslednich zamestnancu na strance HR prehled.
 * Pouziva data pripravena rodicovskou strankou a nevytvari PP ani hlavni layout.
 */
declare(strict_types=1);
?>
<article class="hr_panel hr_panel_wide">
    <div class="hr_panel_header">
        <h2 class="hr_panel_title">Poslední zaměstnanci</h2>
        <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zobrazit všechny</a>
    </div>
    <?php if ($latest === []): ?>
        <p class="hr_empty_state">Zatím není vložený žádný zaměstnanec.</p>
    <?php else: ?>
        <ul class="hr_activity_list">
            <?php foreach ($latest as $employee): ?>
                <li class="hr_activity_item">
                    <span class="hr_dot hr_blue"></span>
                    <strong class="hr_activity_name"><?= h($employee['cele_jmeno']) ?></strong>
                    <span><?= h((string)($employee['zarazeni'] ?? '-')) ?> · <?= h((string)($employee['pracoviste'] ?? '-')) ?></span>
                    <time class="hr_activity_time"><?= h(hr_format_date((string)($employee['zadano'] ?? ''))) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
