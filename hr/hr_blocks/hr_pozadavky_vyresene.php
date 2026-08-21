<?php
/*
 * Ucel souboru: Vykresluje blok vyresenych HR pozadavku.
 * Neobsahuje nacitani dat ani formularove akce.
 */
declare(strict_types=1);

if (!$pozadavkyMuzeCist) {
    return;
}
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div><h2 class="hr_panel_title">Vyřešené požadavky <?= h($pozadavkyRozsah) ?></h2></div>
    </div>

    <?php if ($pozadavkyVyresene === []): ?>
        <p class="hr_empty_state">Bez záznamu</p>
    <?php else: ?>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead><tr>
                    <th class="hr_table_cell hr_table_head">Požadavek</th>
                    <?php if ($pozadavkyZobraziPobocku): ?><th class="hr_table_cell hr_table_head">Pobočka</th><?php endif; ?>
                    <th class="hr_table_cell hr_table_head">Pozice</th>
                    <th class="hr_table_cell hr_table_head">Upřesnění</th>
                    <th class="hr_table_cell hr_table_head">Zadáno</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($pozadavkyVyresene as $pozadavek): ?>
                        <tr>
                            <td class="hr_table_cell">#<?= h($pozadavek['id_pozadavek']) ?></td>
                            <?php if ($pozadavkyZobraziPobocku): ?><td class="hr_table_cell"><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                            <td class="hr_table_cell"><?= h($pozadavek['slot']) ?></td>
                            <td class="hr_table_cell"><?= h($pozadavek['upresneni']) ?></td>
                            <td class="hr_table_cell"><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
