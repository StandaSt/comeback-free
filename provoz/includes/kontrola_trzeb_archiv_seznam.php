<?php
// Kompaktni seznam kontrol a standardni spodni lista pro vice nez dvacet vysledku.
declare(strict_types=1);
?>
<div class="kt_table_wrap"><table class="kt_table kt_archive_table"><thead><tr><th>Pobočka</th><th>Období</th><th>Uložil</th><th>Uloženo</th><th>Akce</th></tr></thead><tbody>
<?php if ($ktList['rows'] === []): ?><tr><td colspan="5">Pro zvolené pobočky nejsou uložené žádné kontroly.</td></tr><?php endif; ?>
<?php foreach ($ktList['rows'] as $row): ?>
  <tr<?= (int)$row['platny'] === 0 ? ' class="kt_invalid"' : '' ?>>
    <td><?= h($ktBranches[(int)$row['id_pob']]['name']) ?></td>
    <td><?= h(cb_format('d', $row['datum_od'])) ?> – <?= h(cb_format('d', $row['datum_do'])) ?><?= (int)$row['platny'] === 0 ? ' · neplatná' : '' ?></td>
    <td><?= h($row['ulozil_jmeno']) ?></td><td><?= h(cb_format('dcs', $row['ulozeno'])) ?></td>
    <td><a href="<?= h(cb_kt_archiv_url($ktFilters, $ktArchiveFilters, ['kt_id'=>$row['id_kontrola'], 'kt_pob'=>$row['id_pob']])) ?>">Detail #<?= h((string)$row['id_kontrola']) ?></a></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($ktList['count'] > 20): ?>
<div class="provoz_objednavky_pager list-bottom">
  <span><?= ($ktList['page'] - 1) * $ktList['per'] + 1 ?>–<?= min($ktList['count'], $ktList['page'] * $ktList['per']) ?> / <?= $ktList['count'] ?></span>
  <div class="provoz_objednavky_pager_links">
  <?php foreach (['«'=>1, '‹'=>max(1,$ktList['page']-1), '›'=>min($ktList['pages'],$ktList['page']+1), '»'=>$ktList['pages']] as $label=>$target):
    $disabled = in_array($label,['«','‹'],true) ? $ktList['page']===1 : $ktList['page']===$ktList['pages']; ?>
    <?php if ($label === '›'): ?><span class="provoz_objednavky_page_current"><?= $ktList['page'] ?> / <?= $ktList['pages'] ?></span><?php endif; ?>
    <a class="provoz_objednavky_page_link<?= $disabled ? ' is-disabled' : '' ?>"<?= $disabled ? ' aria-disabled="true" tabindex="-1"' : '' ?> href="<?= $disabled ? '#' : h(cb_kt_archiv_url($ktFilters,$ktArchiveFilters,['kt_strana'=>$target])) ?>"><?= $label ?></a>
  <?php endforeach; ?>
  </div>
  <div class="provoz_objednavky_per"><label for="kt_per">Řádků</label><select id="kt_per" name="kt_per" form="kt_archive_filters">
    <?php foreach (CB_KT_ARCHIV_PER as $per): ?><option value="<?= $per ?>"<?= $per === $ktList['per'] ? ' selected' : '' ?>><?= $per ?></option><?php endforeach; ?>
  </select></div>
</div>
<?php endif; ?>
