<?php
declare(strict_types=1);
/* Účel: Zobrazí historii pobočky po dnech s HR sloty a rozliší týden bez zveřejněných směn. */
$smHistoryBranches=$smHistoryContext['pobocky'];
$smHistoryBranchId=(int)$smHistoryContext['id_pob'];
$smHistoryBranch=$smHistoryBranches[$smHistoryBranchId]??[];
$smHistoryRows=$smHistoryBranchId>0?cb_smeny_verejne_pobocky($smDb,$smHistoryWeek['start_day'],[$smHistoryBranchId=>$smHistoryBranch]):[];
$smHistoryPositions=cb_smeny_sloty_zobrazene($smHistoryBranch+['sloty_nazvy'=>cb_smeny_sloty_ciselnik($smDb)],array_column($smHistoryRows,'id_slot'));
// Ikona nahrazuje opakovaný nadpis pozice a šetří místo hlavně na telefonu.
$smHistoryPositionIcons=[1=>'🍕',2=>'🚗',3=>'🥣',4=>'🧀',5=>'🏭',6=>'🛎️',8=>'🚗'];
?>
<section class="pp smeny_content <?= cb_smeny_styl_trida($smStyl) ?> smeny_planning" data-module="smeny" data-page="historie_smen">
  <header class="pp_header"><h1>Historie směn</h1>
    <form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="pp_header_controls smeny_history_week">
      <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="historie_smen">
      <select name="branch" aria-label="Pobočka" onchange="this.form.submit()"<?= $smHistoryBranches===[]?' disabled':'' ?>><?php if($smHistoryBranches===[]): ?><option>Bez dostupné pobočky</option><?php else:foreach($smHistoryBranches as $smHistoryBranchOption): ?><option value="<?= (int)$smHistoryBranchOption['id_pob'] ?>"<?= (int)$smHistoryBranchOption['id_pob']===$smHistoryBranchId?' selected':'' ?>><?= h($smHistoryBranchOption['nazev']) ?></option><?php endforeach;endif; ?></select>
      <select name="week" aria-label="Historický týden" onchange="this.form.submit()"><?php foreach($smHistoryWeeks as $smHistoryOption): ?><option value="<?= (int)$smHistoryOption['index'] ?>"<?= $smHistoryOption['index']===$smHistoryWeek['index']?' selected':'' ?>><?= h($smHistoryOption['start']->format('j. n.').'–'.$smHistoryOption['end']->format('j. n. Y')) ?></option><?php endforeach; ?></select>
    </form></header>
  <?php if($smHistoryBranchId>0 && $smHistoryRows===[]): ?><p>Pro tuto pobočku nejsou ve zvoleném týdnu zveřejněné směny.</p><?php endif; ?>
  <div class="smeny_history_days">
  <?php foreach($smHistoryWeek['days'] as $smHistoryDay): ?><article><strong class="smeny_history_day_name"><?= h($smHistoryDay['name']) ?></strong><div class="smeny_history_positions">
    <?php foreach($smHistoryPositions as $smHistorySlotId=>$smHistorySlotName): $smHistorySlotIcon=$smHistoryPositionIcons[(int)$smHistorySlotId]??'👤'; ?><section class="smeny_history_position" aria-label="<?= h($smHistorySlotName) ?>" title="<?= h($smHistorySlotName) ?>">
      <?php $smHistoryFound=false;foreach($smHistoryRows as $smHistoryRow):if($smHistoryRow['datum']!==$smHistoryDay['date']||(int)$smHistoryRow['id_slot']!==(int)$smHistorySlotId)continue;$smHistoryFound=true; ?>
        <div><span class="smeny_history_icon" role="img" aria-label="<?= h($smHistorySlotName) ?>"><?= $smHistorySlotIcon ?></span><span><?= h(substr($smHistoryRow['cas_od'],0,5).'–'.substr($smHistoryRow['cas_do'],0,5)) ?></span><b><?= h($smHistoryRow['pracovnik']) ?></b></div>
      <?php endforeach;if(!$smHistoryFound): ?><small><span aria-hidden="true"><?= $smHistorySlotIcon ?></span><span>—</span></small><?php endif; ?>
    </section><?php endforeach; ?>
  </div></article><?php endforeach; ?>
  </div>
</section>
