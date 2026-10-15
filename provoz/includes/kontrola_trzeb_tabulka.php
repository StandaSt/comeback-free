<?php
// Vykresluje polozky kontroly; hlavicku formulare a ulozeni zarovnava primo ke sloupcum.
declare(strict_types=1);
?>
<div class="kt_table_wrap"><table class="kt_table">
  <?php if (!$ktArchive): ?>
  <caption class="kt_caption">
    <div class="kt_heading"><strong><?= h($ktBranches[$ktFilters['branch']]['name']) ?> · <?= h(cb_format('d', $ktFilters['from'])) ?> – <?= h(cb_format('d', $ktFilters['to'])) ?></strong><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb')) ?>">Archiv kontrol tržeb</a></div>
    <?php require __DIR__ . '/kontrola_trzeb_podklady.php'; ?>
  </caption>
  <?php endif; ?>
  <thead><tr><th>Údaje</th><th>Odevzdané</th><th>Spočítané</th><th>Rozdíl</th><th>Poznámka</th></tr></thead>
  <tbody>
  <?php foreach (cb_kt_polozky() as $key => [$label, $manual]):
      $row = $ktRows[$key];
      $diff = $row['left'] !== null && $row['right'] !== null ? $row['right'] - $row['left'] : null;
      $money = static fn(?int $v): string => $v === null ? '—' : cb_format('p2', cb_kt_decimal($v));
  ?>
    <tr data-kt-row="<?= h($key) ?>" data-left="<?= $row['left'] === null ? '' : h((string)$row['left']) ?>"<?= in_array($key, ['trzba', 'rozdil'], true) ? ' class="kt_total"' : '' ?>>
      <th scope="row"<?= $key === 'rozdil' ? ' title="Rozdíl pokladna. DJ Cash je již zahrnutý v hotovosti."' : '' ?>><?= h($label) ?></th>
      <td><?= h($money($row['left'])) ?></td>
      <td data-kt-right="<?= $row['right'] === null ? '' : h((string)$row['right']) ?>"><?php if (!$ktReadOnly && $manual): ?><input class="kt_amount" type="text" inputmode="decimal" name="spocitane[<?= h($key) ?>]" value="<?= h((string)($ktInput['spocitane'][$key] ?? cb_kt_decimal($row['right']) ?? '')) ?>" aria-label="<?= h($label) ?> – spočítané" autocomplete="off"><?php else: ?><span data-kt-value><?= h($money($row['right'])) ?></span><?php endif; ?></td>
      <td data-kt-diff class="<?= $diff === null ? '' : ($diff < 0 ? 'kt_negative' : 'kt_positive') ?>"><?= h($money($diff)) ?></td>
      <td><?php if (!$ktReadOnly): ?><textarea name="poznamka[<?= h($key) ?>]" rows="1" maxlength="4000" aria-label="<?= h($label) ?> – poznámka"><?= h((string)($ktInput['poznamka'][$key] ?? $row['note'])) ?></textarea><?php else: ?><span class="kt_note"><?= h($row['note']) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if (!$ktArchive && cb_kt_ma_pravo(CB_KT_ULOZIT)): ?>
  <tfoot><tr><td colspan="4"></td><td><button class="kt_save" type="submit"<?= !$ktReady ? ' disabled' : '' ?>>Uložit kontrolu do archivu</button></td></tr></tfoot>
  <?php endif; ?>
</table></div>
