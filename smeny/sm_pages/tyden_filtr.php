<?php
declare(strict_types=1);
/* Účel: Společný kompaktní výběr aktuálního a budoucích týdnů pro čtecí přehledy směn. */
?>
<div class="smeny_planning_filters">
  <form method="get" action="<?= h(cb_root_url('index.php')) ?>">
    <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="<?= h($smReadPage) ?>">
    <label>Týden<select name="week" onchange="this.form.submit()">
      <?php foreach($smReadWeeks as $smFilterIndex=>$smFilterWeek): ?><option value="<?= $smFilterIndex ?>"<?= $smReadWeek['start_day']===$smFilterWeek['start_day']?' selected':'' ?>><?= h($smFilterWeek['start']->format('j. n.').'–'.$smFilterWeek['end']->format('j. n. Y')) ?></option><?php endforeach; ?>
    </select></label>
  </form>
</div>
