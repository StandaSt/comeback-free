<?php
declare(strict_types=1);
/* Účel: Kompaktní osobní přehled hodin z platných reportů IS s rozkliknutím týdne a interním plánem budoucích dnů; vedení bez slotu vidí stav poboček. */
$smReadPage = 'prehled';
?>
<section class="pp smeny_content <?= cb_smeny_styl_trida($smStyl) ?> smeny_dashboard" data-module="smeny" data-page="prehled">
  <header class="pp_header smeny_overview_header"><h1>Přehled směn</h1>
    <details class="smeny_dashboard_appearance"><summary>Vzhled</summary>
      <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=prehled')) ?>" class="smeny_style_switch" data-smeny-style-form>
        <input type="hidden" name="action" value="smeny_styl_ulozit"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <fieldset><legend>Vzhled Směn</legend>
          <label><input type="radio" name="smeny_styl" value="1"<?= $smStyl===1?' checked':'' ?>> Klasický</label>
          <label><input type="radio" name="smeny_styl" value="2"<?= $smStyl===2?' checked':'' ?>> Nový</label>
        </fieldset><span role="status" data-smeny-style-status></span>
      </form>
    </details>
  </header>
  <?php if ($smOverviewPersonal): ?>
    <section class="smeny_dashboard_hours" aria-labelledby="sm-hodiny-title">
      <h2 id="sm-hodiny-title">Přehled odpracovaných směn</h2>
      <p class="smeny_dashboard_hint">Data z platných denních reportů.</p>
      <div class="smeny_dashboard_totals">
        <?php foreach ($smOverviewData['hodiny'] as $smHours): ?>
          <article>
            <?php if ($smHours['detail']): ?><details class="smeny_dashboard_report_detail"><summary class="smeny_dashboard_total_summary"><?php else: ?><div class="smeny_dashboard_total_summary"><?php endif; ?>
            <header><strong><?= h($smHours['label']) ?></strong><small><?= h($smHours['from']->format('j. n.').'–'.$smHours['to']->format('j. n. Y')) ?></small></header>
            <?php if ($smHours['sloty']===[]): ?><span class="smeny_dashboard_empty">0 hod.</span><?php else: ?>
              <ul><?php foreach ($smHours['sloty'] as $smSlotHours): ?><li><strong><?= h(cb_format('h',$smSlotHours['hodiny'])) ?></strong><span><?= h($smSlotHours['slot']) ?></span></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if ($smHours['detail']): ?></summary>
              <div class="smeny_dashboard_report_records">
                <?php if ($smHours['evidence']===[]): ?><p>V tomto týdnu zatím nemáte evidované hodiny v platných denních reportech.</p><?php endif; ?>
                <?php foreach ($smHours['evidence'] as $smRecord): ?>
                  <article><strong><?= h((new DateTimeImmutable($smRecord['datum']))->format('j. n.')) ?></strong>
                    <div><b><?= h(cb_format('h',(float)$smRecord['odpracovano'])) ?> · <?= h($smRecord['slot']) ?></b>
                      <small><?= h($smRecord['pobocka']) ?><?php if ($smRecord['smena_od']!==null && $smRecord['smena_do']!==null): ?> · <?= h(substr($smRecord['smena_od'],0,5).'–'.substr($smRecord['smena_do'],0,5)) ?><?php endif; ?></small>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </details><?php else: ?></div><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php foreach ($smOverviewData['tydny'] as $smOwnWeek): ?>
      <section class="smeny_dashboard_week">
        <header><h2><?= h($smOwnWeek['label']) ?></h2><small><?= h($smOwnWeek['from']->format('j. n.').'–'.$smOwnWeek['to']->format('j. n. Y')) ?></small></header>
        <?php if (!$smOwnWeek['planned']): ?><p class="smeny_dashboard_not_planned">Zatím nenaplánováno</p><?php else: ?>
        <div class="smeny_dashboard_days">
          <?php foreach ($smOwnWeek['days'] as $smOwnDay): ?>
            <article class="<?= $smOwnDay['smeny']===[]?'is-free':'' ?>">
              <header><strong><?= h($smOwnDay['name']) ?></strong><small><?= h($smOwnDay['date']->format('j. n.')) ?></small></header>
              <div><?php if ($smOwnDay['smeny']===[]): ?><span class="smeny_dashboard_empty"><?= $smOwnDay['report']?'Bez evidence v reportech':'Bez naplánované směny' ?></span><?php else: ?>
                <?php foreach ($smOwnDay['smeny'] as $smOwnShift): ?>
                  <div class="smeny_dashboard_shift"><strong><?= $smOwnShift['cas_od']!=='' && $smOwnShift['cas_do']!==''?h($smOwnShift['cas_od'].'–'.$smOwnShift['cas_do']):'Čas neuveden' ?></strong>
                    <span><?= h($smOwnShift['slot']) ?></span>
                    <small><?= $smOwnDay['report']?'Report · '.h(cb_format('h',$smOwnShift['hodiny'])):'Plán' ?> · <?= h($smOwnShift['pobocka']) ?><?= $smOwnShift['cas_od']!=='' && $smOwnShift['cas_do']!=='' && $smOwnShift['cas_do']<$smOwnShift['cas_od']?' · přes půlnoc':'' ?></small>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?></div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php elseif (cb_smeny_planovani_vidi()): ?>
    <?php $smOverviewPlans = cb_smeny_prehled_rozpisy($smDb,$smReadBranches,$smReadWeek['start_day']); ?>
    <?php require __DIR__.'/tyden_filtr.php'; ?>
    <section class="smeny_dashboard_branches">
      <header><h2>Směny po pobočkách</h2><small><?= h($smReadWeek['start']->format('j. n.').'–'.$smReadWeek['end']->format('j. n. Y')) ?></small></header>
      <?php foreach ($smOverviewPlans as $smOverviewPlan): ?>
        <?php // Prázdný zveřejněný týden je také platný rozpis; pracovní kopie není publikací.
        $smPublished = $smOverviewPlan['zverejnena_verze']!==null;
        $smBranchUrl = cb_root_url('index.php?m=smeny&page=naplanovane_smeny&interni=1&week='.(int)$smReadWeek['index'].'&sm_f[pobocka]='.(int)$smOverviewPlan['id_pob']); ?>
        <?php if ($smPublished): ?><a class="smeny_dashboard_branch is-planned" href="<?= h($smBranchUrl) ?>"><strong><?= h($smOverviewPlan['nazev']) ?></strong><span>Směny naplánované <b aria-hidden="true">›</b></span></a>
        <?php else: ?><div class="smeny_dashboard_branch"><strong><?= h($smOverviewPlan['nazev']) ?></strong><span>Zatím nenaplánované</span></div><?php endif; ?>
      <?php endforeach; ?>
      <?php if ($smOverviewPlans===[]): ?><p>Nemáte dostupnou pobočku.</p><?php endif; ?>
    </section>
  <?php else: ?>
    <p class="smeny_notice">Osobní přehled vyžaduje platný pracovní slot v HR. Pro přehled poboček nemáte oprávnění.</p>
  <?php endif; ?>
</section>
