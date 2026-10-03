<?php
declare(strict_types=1);
/* Účel: Zobrazí historii vybrané pobočky po dnech a pracovních pozicích, nezávisle na globální hlavičce. */
$smHistoryBranches=$smHistoryContext['pobocky'];
$smHistoryBranchId=(int)$smHistoryContext['id_pob'];
$smHistoryBranch=$smHistoryBranches[$smHistoryBranchId]??[];
$smHistoryRows=$smHistoryBranchId>0?cb_smeny_verejne_pobocky($smDb,$smHistoryWeek['start_day'],[$smHistoryBranchId=>$smHistoryBranch]):[];
$smHistoryPositions=$smHistoryBranch!==[]?cb_smeny_sablony_pozice($smHistoryBranch):[1=>'Pizzař',2=>'Kurýr'];
// Ikona nahrazuje opakovaný nadpis pozice a šetří místo hlavně na telefonu.
$smHistoryPositionIcons=[1=>'🍕',2=>'🚗',5=>'🏭',8=>'🚗'];
?>
<section class="pp smeny_content smeny_planning" data-module="smeny" data-page="historie_smen">
  <header class="pp_header"><h1>Historie směn</h1>
    <form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="pp_header_controls smeny_history_week">
      <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="historie_smen">
      <select name="branch" aria-label="Pobočka" onchange="this.form.submit()"<?= $smHistoryBranches===[]?' disabled':'' ?>><?php if($smHistoryBranches===[]): ?><option>Bez dostupné pobočky</option><?php else:foreach($smHistoryBranches as $smHistoryBranchOption): ?><option value="<?= (int)$smHistoryBranchOption['id_pob'] ?>"<?= (int)$smHistoryBranchOption['id_pob']===$smHistoryBranchId?' selected':'' ?>><?= h($smHistoryBranchOption['nazev']) ?></option><?php endforeach;endif; ?></select>
      <select name="week" aria-label="Historický týden" onchange="this.form.submit()"><?php foreach($smHistoryWeeks as $smHistoryOption): ?><option value="<?= (int)$smHistoryOption['index'] ?>"<?= $smHistoryOption['index']===$smHistoryWeek['index']?' selected':'' ?>><?= h($smHistoryOption['start']->format('j. n.').'–'.$smHistoryOption['end']->format('j. n. Y')) ?></option><?php endforeach; ?></select>
    </form></header>
  <div class="smeny_history_days">
  <?php foreach($smHistoryWeek['days'] as $smHistoryDay): ?><article><strong class="smeny_history_day_name"><?= h($smHistoryDay['name']) ?></strong>
    <?php foreach($smHistoryPositions as $smHistorySlotId=>$smHistorySlotName): $smHistorySlotIcon=$smHistoryPositionIcons[(int)$smHistorySlotId]??'👤'; ?><section class="smeny_history_position" aria-label="<?= h($smHistorySlotName) ?>">
      <?php $smHistoryFound=false;foreach($smHistoryRows as $smHistoryRow):if($smHistoryRow['datum']!==$smHistoryDay['date']||(int)$smHistoryRow['id_slot']!==(int)$smHistorySlotId)continue;$smHistoryFound=true; ?>
        <div><span class="smeny_history_icon" role="img" aria-label="<?= h($smHistorySlotName) ?>"><?= $smHistorySlotIcon ?></span><span><?= h(substr($smHistoryRow['cas_od'],0,5).'–'.substr($smHistoryRow['cas_do'],0,5)) ?></span><b><?= h($smHistoryRow['pracovnik']) ?></b></div>
      <?php endforeach;if(!$smHistoryFound): ?><small><span aria-hidden="true"><?= $smHistorySlotIcon ?></span><span>—</span></small><?php endif; ?>
    </section><?php endforeach; ?>
  </article><?php endforeach; ?>
  </div>
</section>
