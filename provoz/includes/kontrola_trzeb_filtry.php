<?php
// Horni lista kontroly a checkboxovy vyber povolenych pobocek seznamu archivu.
declare(strict_types=1);
?>
<form class="kt_filters"<?= $ktArchive && empty($ktId) ? ' id="kt_archive_filters"' : '' ?> method="get" action="<?= h(cb_root_url('index.php')) ?>" data-kt-filters>
  <input type="hidden" name="m" value="provoz"><input type="hidden" name="page" value="<?= h($ktArchive ? 'archiv_kontrol_trzeb' : 'kontrola_trzeb') ?>">
  <?php if ($ktArchive && empty($ktId)): ?>
  <input type="hidden" name="kt_vyber" value="1">
  <?php if ($ktList['count'] <= 20): ?><input type="hidden" name="kt_per" value="<?= $ktArchiveFilters['per'] ?>"><?php endif; ?>
  <details class="kt_branch_select"><summary>Pobočky: <?= count($ktArchiveFilters['ids']) === count($ktBranches) ? 'všechny' : count($ktArchiveFilters['ids']) ?></summary>
    <div class="kt_branch_options">
      <label><input type="checkbox" data-kt-all<?= count($ktArchiveFilters['ids']) === count($ktBranches) ? ' checked' : '' ?>>Všechny pobočky</label>
      <?php foreach ($ktBranches as $branch): ?><label><input type="checkbox" name="kt_pobocky[]" value="<?= $branch['id'] ?>"<?= in_array($branch['id'],$ktArchiveFilters['ids'],true) ? ' checked' : '' ?>><?= h($branch['name']) ?></label><?php endforeach; ?>
    </div>
  </details>
  <?php else: ?>
  <label>Pobočka<select name="kt_pob"><?php foreach ($ktBranches as $branch): ?><option value="<?= h((string)$branch['id']) ?>"<?= $ktFilters['branch'] === $branch['id'] ? ' selected' : '' ?>><?= h($branch['name']) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <?php if (!$ktArchive): ?>
  <label>Výběr<select name="kt_rezim"><option value="tyden"<?= $ktFilters['mode'] === 'tyden' ? ' selected' : '' ?>>Týden</option><option value="obdobi"<?= $ktFilters['mode'] === 'obdobi' ? ' selected' : '' ?>>Období</option></select></label>
  <label data-kt-week>Týden<input type="week" name="kt_tyden" value="<?= h($ktFilters['week']) ?>"></label>
  <label data-kt-range>Od<input type="date" name="kt_od" value="<?= h($ktFilters['from']) ?>"></label>
  <label data-kt-range>Do<input type="date" name="kt_do" value="<?= h($ktFilters['to']) ?>"></label>
  <button type="submit" name="kt_posun" value="-1" data-kt-week title="Předchozí týden">‹</button>
  <button type="submit" name="kt_posun" value="1" data-kt-week title="Další týden">›</button>
  <a class="kt_archive_button" href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb')) ?>">Archiv kontrol tržeb</a>
  <?php endif; ?>
  <?php if ($ktArchive && empty($ktId)): ?><label><input type="checkbox" name="kt_neplatne" value="1"<?= !empty($ktInvalid) ? ' checked' : '' ?>>Včetně neplatných</label><?php endif; ?>
</form>
