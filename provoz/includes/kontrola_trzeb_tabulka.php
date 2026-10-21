<?php
// Kompaktni kontrola s povinnymi castkami, prevzetim odevzdanych hodnot a oddelenymi souhrny.
declare(strict_types=1);
?>
<div class="kt_table_wrap"><table class="kt_table">
  <?php if (!$ktArchive): ?>
  <caption class="kt_caption">
    <?php require __DIR__ . '/kontrola_trzeb_podklady.php'; ?>
  </caption>
  <?php endif; ?>
  <thead><tr><th>Údaje</th><th>Odevzdané</th><th>Spočítané</th><th>Rozdíl</th><th>Poznámka</th></tr></thead>
  <tbody>
  <?php foreach (cb_kt_polozky() as $key => [$label, $manual]):
      $row = $ktRows[$key];
      $diff = $row['diff'];
      $money = static fn(?int $v): string => $v === null ? '—' : cb_format('p2', cb_kt_decimal($v));
  ?>
    <tr data-kt-row="<?= h($key) ?>" data-left="<?= $row['left'] === null ? '' : h((string)$row['left']) ?>"<?= in_array($key, ['trzba', 'rozdil'], true) ? ' class="kt_total"' : '' ?>>
      <th scope="row"<?= $key === 'rozdil' ? ' title="Odevzdané: rozdíl z reportů. Spočítané: ruční rozdíly. Rozdíl: ruční rozdíly včetně agregátorů, bez dvojího DJ Cash."' : '' ?>><?= h($label) ?></th>
      <td><?= h($money($row['left'])) ?></td>
      <td data-kt-right="<?= $row['right'] === null ? '' : h((string)$row['right']) ?>" data-kt-manual="<?= $manual ? '1' : '0' ?>"><?php if (!$ktReadOnly && $manual): ?><div class="kt_amount_wrap"><button type="button" class="kt_copy" data-kt-copy title="Převzít Odevzdané – <?= h($label) ?>" aria-label="Převzít Odevzdané – <?= h($label) ?>"<?= $row['left'] === null ? ' disabled' : '' ?>>→</button><input class="kt_amount" type="text" inputmode="decimal" name="spocitane[<?= h($key) ?>]" value="<?= h((string)($ktInput['spocitane'][$key] ?? cb_kt_decimal($row['right']) ?? '')) ?>" aria-label="<?= h($label) ?> – spočítané" autocomplete="off" required></div><?php else: ?><span data-kt-value><?= h($money($row['right'])) ?></span><?php endif; ?></td>
      <td data-kt-diff class="<?= $diff === null ? '' : ($diff < 0 ? 'kt_negative' : 'kt_positive') ?>"><?= h($money($diff)) ?></td>
      <td><?php if (!$ktReadOnly): ?><textarea name="poznamka[<?= h($key) ?>]" rows="1" maxlength="4000" aria-label="<?= h($label) ?> – poznámka"><?= h((string)($ktInput['poznamka'][$key] ?? $row['note'])) ?></textarea><?php else: ?><span class="kt_note"><?= h($row['note']) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if (!$ktArchive && cb_kt_ma_pravo(CB_KT_ULOZIT)): ?>
  <tfoot><tr><td colspan="4"></td><td><button class="kt_save" type="submit"<?= !$ktReady ? ' disabled' : '' ?>>Uložit kontrolu do archivu</button></td></tr></tfoot>
  <?php endif; ?>
</table></div>
