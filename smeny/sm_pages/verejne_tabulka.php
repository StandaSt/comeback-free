<?php
declare(strict_types=1);
/* Účel: Vykreslí kompaktní denní bloky s vždy viditelnou akceptací, vlastní souhrn a navigaci poboček ve společném formuláři filtrů IS. */
$smView=cb_smeny_verejne_prehled($smPublicRows,$smOwnRows,$smPublicBranches,$_GET,$smPublicPerson,!$smAll,$smOwnBranches);
$smFilters=$smView['filters'];
// Vybraný slot zůstane viditelný i v prázdném týdnu; ostatní nabídka vychází ze skutečných směn.
if ($smFilters['slot']>0 && !isset($smView['slots'][$smFilters['slot']])) {
    $smView['slots'][$smFilters['slot']]=$smPublicSlots[$smFilters['slot']]??('Slot #'.$smFilters['slot']);
}
$smDayNames=[1=>'Pondělí',2=>'Úterý',3=>'Středa',4=>'Čtvrtek',5=>'Pátek',6=>'Sobota',7=>'Neděle'];
$smReset=cb_smeny_verejne_odkaz($smPage,$smPublicWeekIndex,$smInternalOnly,[]);
?>
<form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="smeny_public_view" autocomplete="off">
  <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="<?= h($smPage) ?>"><input type="hidden" name="sm_p" value="1">
  <?php if($smInternalOnly): ?><input type="hidden" name="interni" value="1"><?php endif; ?>
  <?php // Společný handler vymění tento celek, aby filtry, šipky i bloky vždy odpovídaly téže odpovědi. ?>
  <div class="table-wrap smeny_public_body">
    <input type="hidden" name="sm_zob_pob" value="<?= $smView['current'] ?>">
    <div class="smeny_public_toolbar">
      <label class="smeny_public_week">Týden<select name="week" onchange="this.form.requestSubmit()">
        <?php foreach($smPublicWeeks as $smIndex=>$smWeek): ?><option value="<?= $smIndex ?>"<?= $smIndex===$smPublicWeekIndex?' selected':'' ?>><?= h($smWeek['start']->format('j. n.').'–'.$smWeek['end']->format('j. n. Y')) ?></option><?php endforeach; ?>
      </select></label>
      <div class="smeny_public_actions">
        <label class="smeny_public_own"><input type="checkbox" class="filter-input" name="sm_f[moje]" value="1"<?= $smView['own']?' checked':'' ?><?= !$smAll || $smPublicPerson<=0?' disabled':'' ?>>Pouze mé směny</label>
        <a class="filter-reset-btn smeny_public_button smeny_public_reset" href="<?= h($smReset) ?>" aria-label="Vyprázdnit filtry">x filtry</a>
      </div>
    </div>
    <div class="smeny_public_filters">
      <label>Pobočka<select class="filter-input" name="sm_f[pobocka]" aria-label="Filtrovat pobočku">
        <?php if(count($smView['options'])>1): ?><option value="0"<?= $smFilters['pobocka']===0?' selected':'' ?>>Všechny pobočky</option><?php endif; ?>
        <?php if($smView['options']===[]): ?><option value="0">Žádná pobočka</option><?php endif; ?>
        <?php foreach($smView['options'] as $smId=>$smName): ?><option value="<?= $smId ?>"<?= $smFilters['pobocka']===$smId?' selected':'' ?>><?= h($smName) ?></option><?php endforeach; ?>
      </select></label>
      <label>Den<select class="filter-input" name="sm_f[den]" aria-label="Filtrovat den"><option value="0">Všechny dny</option><?php foreach($smDayNames as $smNumber=>$smName): ?><option value="<?= $smNumber ?>"<?= $smFilters['den']===$smNumber?' selected':'' ?>><?= h($smName) ?></option><?php endforeach; ?></select></label>
      <label>Slot<select class="filter-input" name="sm_f[slot]" aria-label="Filtrovat slot"><option value="0">Všechny</option><?php foreach($smView['slots'] as $smId=>$smName): ?><option value="<?= $smId ?>"<?= $smFilters['slot']===$smId?' selected':'' ?>><?= h($smName) ?></option><?php endforeach; ?></select></label>
      <label>Jméno<input type="search" class="filter-input" name="sm_f[jmeno]" aria-label="Filtrovat jméno" placeholder="Příjmení nebo jméno" value="<?= h($smFilters['jmeno']) ?>"<?= $smView['own']?' disabled':'' ?>></label>
    </div>
    <?php if($smView['browse']): ?>
      <nav class="smeny_public_branches" aria-label="Přepínání poboček">
        <?php foreach(['previous'=>'←','next'=>'→'] as $smDirection=>$smArrow): ?>
          <?php if($smDirection==='next'): ?><strong><?= h($smView['options'][$smView['current']]??'') ?></strong><?php endif; ?>
          <?php if($smView[$smDirection]>0): ?><a class="smeny_public_button" href="<?= h(cb_smeny_verejne_odkaz($smPage,$smPublicWeekIndex,$smInternalOnly,$smFilters,$smView[$smDirection])) ?>" aria-label="<?= $smDirection==='previous'?'Předchozí pobočka':'Další pobočka' ?>"><?= $smArrow ?></a>
          <?php else: ?><span class="smeny_public_button" aria-disabled="true"><?= $smArrow ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <div class="smeny_public_days" aria-live="polite">
      <?php foreach($smPublicWeek['days'] as $smDay): ?>
        <?php if($smFilters['den']>0 && (int)(new DateTimeImmutable($smDay['date']))->format('N')!==$smFilters['den'])continue; ?>
        <article class="smeny_public_day">
          <h2><?= h($smDay['name'].' '.$smDay['date_label']) ?></h2>
          <?php $smDayRows=$smView['days'][$smDay['date']]??[]; ?>
          <?php if($smDayRows===[]): ?><p class="smeny_public_empty">Bez směn<?= $smFilters['slot']>0 || $smFilters['jmeno']!==''?' pro vybrané filtry':'' ?></p><?php else: ?>
          <ul class="smeny_public_rows<?= $smView['own']?' smeny_public_rows--own':'' ?>">
            <?php foreach($smDayRows as $smRow): ?>
              <li>
                <?php if($smView['own']): ?><span class="smeny_public_time"><?= h(substr($smRow['cas_od'],0,5).'–'.substr($smRow['cas_do'],0,5)) ?></span><strong class="smeny_public_name"><?= h($smRow['pobocka']) ?></strong><span class="smeny_public_slot"><?= h($smRow['slot']) ?></span>
                <?php else: ?><span class="smeny_public_name"><?= h($smRow['pracovnik']) ?></span><span class="smeny_public_slot"><?= h($smRow['slot']) ?></span><span class="smeny_public_time"><?= h(substr($smRow['cas_od'],0,5).'–'.substr($smRow['cas_do'],0,5)) ?></span><?php endif; ?>
                <?php $smState=cb_smeny_verejne_stav($smRow); ?>
                <span class="smeny_public_state smeny_public_state--<?= h($smState['kind']) ?>" title="<?= h($smState['detail']) ?>" aria-label="Akceptace: <?= h($smState['detail']) ?>"><?= h($smState['label']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</form>
