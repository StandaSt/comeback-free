<?php
declare(strict_types=1);

$employees = hr_fetch_employees($db);
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div>
            <h2 class="hr_panel_title">Seznam zaměstnanců</h2>
            <p class="hr_muted">Reálná data z HR evidence</p>
        </div>
        <a class="hr_primary_button hr_panel_button_primary" href="<?= h(cb_root_url('index.php?m=hr&page=novy_zamestnanec')) ?>">+ Nový zaměstnanec</a>
    </div>

    <?php if ($employees === []): ?>
        <p class="hr_empty_state">Zatím není vložený žádný zaměstnanec.</p>
    <?php else: ?>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead>
                    <tr>
                        <th class="hr_table_cell hr_table_head">Zaměstnanec</th>
                        <th class="hr_table_cell hr_table_head">Zařazení</th>
                        <th class="hr_table_cell hr_table_head">Pracoviště</th>
                        <th class="hr_table_cell hr_table_head">Typ vztahu</th>
                        <th class="hr_table_cell hr_table_head">Datum nástupu</th>
                        <th class="hr_table_cell hr_table_head">Stav</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td class="hr_table_cell"><a class="hr_table_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']))) ?>"><?= h($employee['cele_jmeno']) ?></a></td>
                            <td class="hr_table_cell"><?= h((string)($employee['zarazeni'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h((string)($employee['pracoviste'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h((string)($employee['vztah_kod'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></td>
                            <td class="hr_table_cell"><span class="hr_badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
