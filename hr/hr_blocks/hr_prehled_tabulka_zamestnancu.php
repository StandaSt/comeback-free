<?php
/*
 * Ucel souboru: Vykresluje tabulku poslednich zaznamu zamestnancu na HR prehledu.
 * Pouziva data pripravena rodicovskou strankou a nevytvari PP ani hlavni layout.
 */
declare(strict_types=1);
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <h2 class="hr_panel_title">Seznam posledních záznamů</h2>
        <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zobrazit všechny</a>
    </div>
    <?php if ($latest === []): ?>
        <p class="hr_empty_state">HR evidence je připravená, ale zatím neobsahuje žádná data.</p>
    <?php else: ?>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead>
                    <tr>
                        <th class="hr_table_cell hr_table_head">Zaměstnanec</th>
                        <th class="hr_table_cell hr_table_head">Pracoviště</th>
                        <th class="hr_table_cell hr_table_head">Zařazení</th>
                        <th class="hr_table_cell hr_table_head">Datum nástupu</th>
                        <th class="hr_table_cell hr_table_head">Typ vztahu</th>
                        <th class="hr_table_cell hr_table_head">Stav</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latest as $employee): ?>
                        <tr>
                            <td class="hr_table_cell"><a class="hr_table_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']))) ?>"><?= h($employee['cele_jmeno']) ?></a></td>
                            <td class="hr_table_cell"><?= h((string)($employee['pracoviste'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h((string)($employee['zarazeni'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></td>
                            <td class="hr_table_cell"><?= h((string)($employee['vztah_kod'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><span class="hr_badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
