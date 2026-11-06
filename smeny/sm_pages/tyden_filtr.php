<?php
declare(strict_types=1);
/* Účel: Kompaktní výběr týdne; v interním rozpisu zachová zdroj IS a vybranou povolenou pobočku. */
?>
<div class="smeny_planning_filters">
  <form method="get" action="<?= h(cb_root_url('index.php')) ?>">
    <?php if (!empty($smInternalOnly)): ?>
      <input type="hidden" name="interni" value="1">
      <?php $smWeekBranch = cb_smeny_verejne_filtry_hodnoty($_GET)['pobocka']; ?>
      <?php if (isset($smPublicBranches[$smWeekBranch])): ?><input type="hidden" name="sm_f[pobocka]" value="<?= $smWeekBranch ?>"><?php endif; ?>
    <?php endif; ?>
    <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="<?= h($smReadPage) ?>">
    <label>Týden<select name="week" onchange="this.form.submit()">
      <?php foreach($smReadWeeks as $smFilterIndex=>$smFilterWeek): ?><option value="<?= $smFilterIndex ?>"<?= $smReadWeek['start_day']===$smFilterWeek['start_day']?' selected':'' ?>><?= h($smFilterWeek['start']->format('j. n.').'–'.$smFilterWeek['end']->format('j. n. Y')) ?></option><?php endforeach; ?>
    </select></label>
  </form>
</div>
