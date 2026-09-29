<?php
declare(strict_types=1);

/* Účel souboru: Zobrazí klikací časovou osu, výběr pracovníka a bezpečný stavový tok plánování týdne. */

$smPlanBranchId=(int)($_GET['id_pob'] ?? array_key_first($smPlanBranches) ?? 0);
$smPlanWeekIndex=max(0,min(3,(int)($_GET['week'] ?? 0)));
$smPlanWeek=$smPlanWeeks[$smPlanWeekIndex];
$smPlanNow=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
$smPlanCanStart=$smPlanNow >= $smPlanWeek['deadline'];
$smPlanPlanningDeadline=$smPlanWeek['start']->modify('-3 days')->setTime(20,0);
$smPlanBranch=$smPlanBranches[$smPlanBranchId] ?? null;
$smPlan=$smPlanBranch ? cb_smeny_planovani_nacist($smDb,$smPlanBranchId,(string)$smPlanWeek['start_day']) : null;
$smPlanTemplates=$smPlanBranch ? array_values(array_filter(cb_smeny_sablony_seznam($smDb,[$smPlanBranchId=>$smPlanBranch]),static fn(array $row):bool=>(int)$row['id_pob']===$smPlanBranchId)) : [];
$smPlanStateLabels=['rozpracovany'=>'Rozpracováno','pripraveny'=>'Týden je naplánován','zverejneny'=>'Zveřejněno','zmeneny_po_zverejneni'=>'Změněno po zveřejnění'];
$smPlanFlash=cb_smeny_sablony_flash_nacist();
$smPlanCandidates=[];
$smPlanHasAssignments=false;
if ($smPlan!==null && $smPlanBranch!==null) {
    foreach ($smPlan['blocks'] as $block) if (!empty($block['id_person'])) $smPlanHasAssignments=true;
    foreach ($smPlanWeek['days'] as $day) {
        foreach (cb_smeny_sablony_pozice($smPlanBranch) as $idSlot=>$slotName) {
            $smPlanCandidates[$day['date'].':'.$idSlot]=cb_smeny_planovani_kandidati($smDb,$smPlan,(string)$day['date'],(int)$idSlot);
        }
    }
}
?>
<section class="pp smeny_content smeny_planning" data-module="smeny" data-page="planovani_smen">
  <header class="pp_header"><h1>Plánování směn</h1></header>
  <form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="smeny_planning_filters">
    <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="planovani_smen">
    <label>Pobočka<select name="id_pob" onchange="this.form.submit()"><?php foreach($smPlanBranches as $row): ?><option value="<?= (int)$row['id_pob'] ?>"<?= (int)$row['id_pob']===$smPlanBranchId?' selected':'' ?>><?= h((string)$row['nazev']) ?></option><?php endforeach; ?></select></label>
    <label>Týden<select name="week" onchange="this.form.submit()"><?php foreach($smPlanWeeks as $index=>$week): ?><option value="<?= $index ?>"<?= $index===$smPlanWeekIndex?' selected':'' ?>><?= h($week['start']->format('j. n.').'–'.$week['end']->format('j. n. Y').($smPlanNow < $week['deadline']?' – požadavky otevřené':'')) ?></option><?php endforeach; ?></select></label>
  </form>
  <?php if($smPlanFlash): ?><p class="smeny_notice smeny_notice--<?= $smPlanFlash['type']==='success'?'success':'error' ?>"><?= h($smPlanFlash['text']) ?></p><?php endif; ?>
  <?php if(!$smPlanBranch): ?><p class="smeny_notice smeny_notice--error">Nemáte dostupnou pobočku pro plánování.</p>
  <?php elseif($smPlan===null): ?>
    <div class="smeny_planning_empty"><p>Pro tento týden zatím není založený interní rozpis.</p><p>Požadavky se uzavírají <?= h($smPlanWeek['deadline']->format('j. n. Y \v H:i')) ?>. Plán má být hotový do <?= h($smPlanPlanningDeadline->format('j. n. Y \v H:i')) ?>.</p>
    <?php if(!$smPlanCanStart): ?><p class="smeny_notice">Plánování se otevře až po uzávěrce požadavků.</p><?php elseif(cb_smeny_planovani_edituje()): ?><form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$smPlanBranchId.'&week='.$smPlanWeekIndex)) ?>"><input type="hidden" name="action" value="smeny_plan_zalozit"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="id_pob" value="<?= $smPlanBranchId ?>"><input type="hidden" name="week" value="<?= $smPlanWeekIndex ?>"><label>Výchozí šablona<select name="id_smeny_sablona" required><?php foreach($smPlanTemplates as $template): ?><option value="<?= (int)$template['id_smeny_sablona'] ?>"><?= h((string)$template['nazev']) ?></option><?php endforeach; ?></select></label><button class="smeny_primary_button"<?= $smPlanTemplates===[]?' disabled':'' ?>>Začít plánovat</button></form><?php endif; ?></div>
  <?php else: ?>
    <div class="smeny_planning_meta"><strong><?= h($smPlanStateLabels[(string)$smPlan['stav']]??(string)$smPlan['stav']) ?></strong><span>Šablona: <?= h((string)($smPlan['sablona_nazev']??'bez šablony')) ?></span></div>
    <?php if((string)$smPlan['stav']==='rozpracovany' && !$smPlanHasAssignments && cb_smeny_planovani_edituje()): ?>
      <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$smPlanBranchId.'&week='.$smPlanWeekIndex)) ?>" class="smeny_planning_template_reload">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="id_smeny_rozpis" value="<?= (int)$smPlan['id_smeny_rozpis'] ?>"><input type="hidden" name="id_pob" value="<?= $smPlanBranchId ?>"><input type="hidden" name="week" value="<?= $smPlanWeekIndex ?>">
        <label>Šablona<select name="id_smeny_sablona"><?php foreach($smPlanTemplates as $template): ?><option value="<?= (int)$template['id_smeny_sablona'] ?>"<?= (int)$template['id_smeny_sablona']===(int)$smPlan['id_smeny_sablona']?' selected':'' ?>><?= h((string)$template['nazev']) ?></option><?php endforeach; ?></select></label>
        <button name="action" value="smeny_plan_sablona_obnovit" class="smeny_secondary_button">Načíst vybranou šablonu znovu</button><small>Dokud není přidaný první zaměstnanec, lze šablonu změnit nebo načíst její aktuální úpravy.</small>
      </form>
    <?php endif; ?>
    <?php if((string)$smPlan['stav']==='rozpracovany' && cb_smeny_planovani_edituje()): ?>
      <p class="smeny_planning_help"><strong>Jak plánovat:</strong> 1. klikněte na začátek směny, 2. vyberte zaměstnance, 3. klikněte na čas konce ve stejném řádku. Barevné pole je pouze doporučení šablony.</p>
      <div class="smeny_planner" data-smeny-planner>
        <div class="smeny_planner_days">
        <?php foreach($smPlanWeek['days'] as $dayIndex=>$day): $dayNumber=$dayIndex+1; $closing=cb_smeny_sablony_zaviraci_cas($smPlanBranch,$dayNumber); $closingMinutes=$closing!==''?cb_smeny_sablony_cas_minuty($closing,true):1440; ?>
          <article class="smeny_planner_day">
            <header><strong><?= h($day['name'].' '.$day['date_label']) ?></strong><small>zavírá <?= h($closing!==''?$closing:'neuvedeno') ?></small></header>
            <?php foreach(cb_smeny_sablony_pozice($smPlanBranch) as $idSlot=>$slotName):
              $guides=array_values(array_filter($smPlan['blocks'],static fn(array $b):bool=>(string)$b['datum']===(string)$day['date'] && (int)$b['id_slot']===(int)$idSlot && !empty($b['id_smeny_sablona_blok'])));
              $assignments=array_values(array_filter($smPlan['blocks'],static fn(array $b):bool=>(string)$b['datum']===(string)$day['date'] && (int)$b['id_slot']===(int)$idSlot && !empty($b['id_person'])));
            ?>
              <div class="smeny_planner_row" data-smeny-row data-date="<?= h((string)$day['date']) ?>" data-slot="<?= (int)$idSlot ?>" data-slot-name="<?= h($slotName) ?>">
                <div class="smeny_planner_row_title"><strong><?= h($slotName) ?></strong><?php foreach($assignments as $assigned): ?><span class="smeny_planner_assignment"><?= h(substr((string)$assigned['cas_od'],0,5).'–'.substr((string)$assigned['cas_do'],0,5).' '.$assigned['pracovnik']) ?></span><?php endforeach; ?></div>
                <div class="smeny_planner_timeline">
                <?php for($minute=600;$minute<=$closingMinutes;$minute+=30): $clock=$minute%1440; $time=sprintf('%02d:%02d',intdiv($clock,60),$clock%60); $guideCount=0; foreach($guides as $guide){$guideFrom=cb_smeny_sablony_cas_minuty(substr((string)$guide['cas_od'],0,5),false);$guideTo=cb_smeny_sablony_cas_minuty(substr((string)$guide['cas_do'],0,5),true);if($minute>=$guideFrom && $minute<$guideTo)$guideCount++;} ?><button type="button" class="smeny_planner_tick<?= $guideCount>0?' is-guide':'' ?>" data-smeny-time="<?= h($time) ?>" title="<?= h($time.($guideCount>0?' · šablona '.$guideCount.'×':'')) ?>"><?php if($guideCount>1): ?><em><?= $guideCount ?></em><?php endif; ?><span><?= h($time) ?></span></button><?php endfor; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </article>
        <?php endforeach; ?>
        </div>
        <aside class="smeny_planner_people" data-smeny-people hidden>
          <header><strong data-smeny-selection-title>Vyberte zaměstnance</strong><button type="button" data-smeny-cancel aria-label="Zrušit výběr">×</button></header>
          <label class="smeny_planner_ignore"><input type="checkbox" data-smeny-ignore> Bez ohledu na požadavky</label>
          <p data-smeny-people-help>Nejprve jsou lidé s hlavní pobočkou zde, potom pracovníci z ostatních poboček.</p>
          <div data-smeny-people-list></div>
        </aside>
        <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$smPlanBranchId.'&week='.$smPlanWeekIndex)) ?>" data-smeny-assignment-form hidden>
          <input type="hidden" name="action" value="smeny_plan_priradit"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="id_smeny_rozpis" value="<?= (int)$smPlan['id_smeny_rozpis'] ?>"><input type="hidden" name="id_pob" value="<?= $smPlanBranchId ?>"><input type="hidden" name="week" value="<?= $smPlanWeekIndex ?>"><input type="hidden" name="datum"><input type="hidden" name="id_slot"><input type="hidden" name="cas_od"><input type="hidden" name="cas_do"><input type="hidden" name="id_person"><input type="hidden" name="bez_ohledu" value="0">
        </form>
        <script type="application/json" data-smeny-candidates><?= json_encode($smPlanCandidates,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP) ?></script>
      </div>
    <?php else: ?>
      <div class="smeny_planning_days"><?php foreach($smPlanWeek['days'] as $day): ?><article><strong><?= h($day['name'].' '.$day['date_label']) ?></strong><?php $found=false; foreach($smPlan['blocks'] as $block){if((string)$block['datum']!==$day['date'] || empty($block['id_person']))continue;$found=true; ?><div class="smeny_planning_block"><span><?= h(substr((string)$block['cas_od'],0,5).'–'.substr((string)$block['cas_do'],0,5)) ?></span><span><?= h(cb_smeny_sablony_nazev_pozice($smPlanBranch,(int)$block['id_slot'])) ?></span><strong><?= h((string)$block['pracovnik']) ?></strong></div><?php } if(!$found): ?><small>Bez naplánovaných směn</small><?php endif; ?></article><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if(cb_smeny_planovani_edituje()): ?><form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$smPlanBranchId.'&week='.$smPlanWeekIndex)) ?>" class="smeny_planning_actions"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="id_smeny_rozpis" value="<?= (int)$smPlan['id_smeny_rozpis'] ?>"><input type="hidden" name="id_pob" value="<?= $smPlanBranchId ?>"><input type="hidden" name="week" value="<?= $smPlanWeekIndex ?>"><?php if($smPlan['stav']==='rozpracovany'): ?><button name="action" value="smeny_plan_pripraven" class="smeny_primary_button">Týden je naplánován, uložit</button><?php elseif($smPlan['stav']==='pripraveny'): ?><button name="action" value="smeny_plan_upravit" class="smeny_secondary_button">Ještě to upravím</button><?php if(cb_smeny_planovani_publikuje()): ?><button type="button" class="smeny_primary_button" disabled title="Zpřístupní se po dokončení obsazování">Publikovat – zveřejnit směny</button><?php endif; ?><?php endif; ?></form><?php endif; ?>
  <?php endif; ?>
</section>
