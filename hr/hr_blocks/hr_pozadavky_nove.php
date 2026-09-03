<?php
/*
 * Ucel souboru: Vykresluje blok novych HR pozadavku.
 * Neobsahuje nacitani dat ani zpracovani formulare pro zruseni.
 */
declare(strict_types=1);

if (!$pozadavkyMuzeCist) {
    return;
}
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div><h2 class="hr_panel_title">Nové požadavky <?= h($pozadavkyRozsah) ?></h2></div>
    </div>

    <?php if ($pozadavkyNove === []): ?>
        <p class="hr_empty_state">Žádné zadané požadavky</p>
    <?php else: ?>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead><tr>
                    <th class="hr_table_cell hr_table_head">Požadavek</th>
                    <?php if ($pozadavkyZobraziPobocku): ?><th class="hr_table_cell hr_table_head">Pobočka</th><?php endif; ?>
                    <th class="hr_table_cell hr_table_head">Pozice</th>
                    <th class="hr_table_cell hr_table_head">Upřesnění</th>
                    <th class="hr_table_cell hr_table_head">Zadáno</th>
                    <?php if ($pozadavkyMuzeZrusit): ?><th class="hr_table_cell hr_table_head">Akce</th><?php endif; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($pozadavkyNove as $pozadavek): ?>
                        <tr>
                            <td class="hr_table_cell">#<?= h($pozadavek['id_pozadavek']) ?></td>
                            <?php if ($pozadavkyZobraziPobocku): ?><td class="hr_table_cell"><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                            <td class="hr_table_cell"><?= h($pozadavek['slot']) ?></td>
                            <td class="hr_table_cell"><?= h($pozadavek['upresneni']) ?></td>
                            <td class="hr_table_cell"><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            <?php if ($pozadavkyMuzeZrusit): ?>
                                <td class="hr_table_cell">
                                    <?php if ((int)$pozadavek['id_user_zadal'] === $pozadavkyUserId): ?>
                                        <form method="post" action="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" class="hr_row_action_form">
                                            <input type="hidden" name="cb_action" value="hr_pozadavek_zrusit">
                                            <input type="hidden" name="id_pozadavek" value="<?= h($pozadavek['id_pozadavek']) ?>">
                                            <button class="hr_delete_button" type="submit" title="Odstranit" aria-label="Odstranit">×</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
