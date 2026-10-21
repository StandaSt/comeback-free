<?php
// Automaticke filtry pobocky a obdobi; archiv umoznuje zahrnout take neplatne kontroly.
declare(strict_types=1);
?>
<form class="kt_filters" method="get" action="<?= h(cb_root_url('index.php')) ?>" data-kt-filters>
  <input type="hidden" name="m" value="provoz"><input type="hidden" name="page" value="<?= h($ktArchive ? 'archiv_kontrol_trzeb' : 'kontrola_trzeb') ?>">
  <label>Pobočka<select name="kt_pob"><?php foreach ($ktBranches as $branch): ?><option value="<?= h((string)$branch['id']) ?>"<?= $ktFilters['branch'] === $branch['id'] ? ' selected' : '' ?>><?= h($branch['name']) ?></option><?php endforeach; ?></select></label>
  <?php if (!$ktArchive): ?>
  <label>Výběr<select name="kt_rezim"><option value="tyden"<?= $ktFilters['mode'] === 'tyden' ? ' selected' : '' ?>>Týden</option><option value="obdobi"<?= $ktFilters['mode'] === 'obdobi' ? ' selected' : '' ?>>Období</option></select></label>
  <label data-kt-week>Týden<input type="week" name="kt_tyden" value="<?= h($ktFilters['week']) ?>"></label>
  <label data-kt-range>Od<input type="date" name="kt_od" value="<?= h($ktFilters['from']) ?>"></label>
  <label data-kt-range>Do<input type="date" name="kt_do" value="<?= h($ktFilters['to']) ?>"></label>
  <button type="submit" name="kt_posun" value="-1" data-kt-week title="Předchozí týden">‹</button>
  <button type="submit" name="kt_posun" value="1" data-kt-week title="Další týden">›</button>
  <?php endif; ?>
  <?php if ($ktArchive): ?><label><input type="checkbox" name="kt_neplatne" value="1"<?= !empty($ktInvalid) ? ' checked' : '' ?>>Včetně neplatných</label><?php endif; ?>
</form>
