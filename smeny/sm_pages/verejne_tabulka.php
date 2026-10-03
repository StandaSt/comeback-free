<?php
declare(strict_types=1);
/* Účel: Vykreslí kompaktní tabulku směn se společným filtrem z provoz/js/filtry.js. */
$smTableBranches=cb_smeny_verejne_moznosti($smPublicRowsAll,'id_pob','pobocka');
$smTableSlots=cb_smeny_verejne_moznosti($smPublicRowsAll,'id_slot','slot');
$smTableFilters=cb_smeny_verejne_filtry_hodnoty($_GET);
$smPublicRows=cb_smeny_verejne_filtrovat($smPublicRowsAll,$_GET);
$smDayNames=[1=>'Pondělí',2=>'Úterý',3=>'Středa',4=>'Čtvrtek',5=>'Pátek',6=>'Sobota',7=>'Neděle'];
$smTableResetUrl=cb_root_url('index.php?m=smeny&page='.$smPage.'&week='.(int)$smTableWeekIndex);
?>
<form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="cb_table_view smeny_shift_table_view" autocomplete="off">
  <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="<?= h($smPage) ?>"><input type="hidden" name="week" value="<?= (int)$smTableWeekIndex ?>"><input type="hidden" name="sm_p" value="1">
  <div class="cb_table_wrap table-wrap"><table class="cb_table smeny_shift_table">
  <thead>
    <tr class="filter-row">
      <th><select class="filter-input" name="sm_f[den]" aria-label="Filtrovat den"><option value="">Všechny dny</option><?php foreach($smDayNames as $smDayNumber=>$smDayName): ?><option value="<?= $smDayNumber ?>"<?= $smTableFilters['den']===$smDayNumber?' selected':'' ?>><?= h($smDayName) ?></option><?php endforeach; ?></select></th>
      <th><div class="filter-actions"><a class="filter-reset-btn" href="<?= h($smTableResetUrl) ?>" aria-label="Zrušit filtr" title="Zrušit filtr">&times;</a></div></th>
      <th><select class="filter-input" name="sm_f[pobocka]" aria-label="Filtrovat pobočku"><option value="">Všechny</option><?php foreach($smTableBranches as $smBranchId=>$smBranchName): ?><option value="<?= $smBranchId ?>"<?= $smTableFilters['pobocka']===$smBranchId?' selected':'' ?>><?= h($smBranchName) ?></option><?php endforeach; ?></select></th>
      <th><select class="filter-input" name="sm_f[slot]" aria-label="Filtrovat slot"><option value="">Všechny</option><?php foreach($smTableSlots as $smSlotId=>$smSlotName): ?><option value="<?= $smSlotId ?>"<?= $smTableFilters['slot']===$smSlotId?' selected':'' ?>><?= h($smSlotName) ?></option><?php endforeach; ?></select></th>
      <th><input class="filter-input" type="search" name="sm_f[jmeno]" value="<?= h($smTableFilters['jmeno']) ?>" aria-label="Filtrovat jméno" placeholder="Příjmení nebo jméno"></th>
    </tr>
    <tr><th>Den</th><th>Čas</th><th>Pobočka</th><th>Slot</th><th>Příjmení a jméno</th></tr>
  </thead><tbody>
  <?php foreach($smPublicRows as $smTableRow): $smTableDate=new DateTimeImmutable($smTableRow['datum']); ?>
    <tr><td><?= h($smDayNames[(int)$smTableDate->format('N')].' '.$smTableDate->format('j. n. Y')) ?></td><td><?= h(substr($smTableRow['cas_od'],0,5).'–'.substr($smTableRow['cas_do'],0,5)) ?></td><td><?= h($smTableRow['pobocka']) ?></td><td><?= h($smTableRow['slot']) ?></td><td><strong><?= h($smTableRow['pracovnik']) ?></strong></td></tr>
  <?php endforeach; ?>
  <?php if($smPublicRows===[]): ?><tr><td colspan="5">Vybraným filtrům neodpovídá žádná směna.</td></tr><?php endif; ?>
  </tbody></table></div>
</form>
