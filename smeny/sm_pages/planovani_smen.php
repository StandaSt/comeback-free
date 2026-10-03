<?php
declare(strict_types=1);
/* Účel: Zobrazí plánování V2, potřebu ze šablony, zámek pobočky a vyřízení žádostí o zrušení. */
$smPlanBranchId = (int)($_GET['id_pob'] ?? array_key_first($smPlanBranches) ?? 0);
$smPlanWeekIndex = max(0,min(count($smPlanWeeks)-1,(int)($_GET['week'] ?? 1)));
$smPlanWeek = $smPlanWeeks[$smPlanWeekIndex];
$smPlanBranch = $smPlanBranches[$smPlanBranchId] ?? null;
$smPlanNow = new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
$smPlanCanStart = $smPlanNow >= $smPlanWeek['deadline'];
$smPlan = $smPlanBranch ? cb_smeny_planovani_nacist($smDb,$smPlanBranchId,$smPlanWeek['start_day']) : null;
$smPlanTemplates = $smPlanBranch ? cb_smeny_sablony_seznam($smDb,[$smPlanBranchId=>$smPlanBranch]) : [];
$smPlanPerson = cb_smeny_sablony_id_person($smDb);
$smPlanLock = $smPlanBranch ? cb_smeny_planovani_zamek_nacist($smDb,$smPlanBranchId) : null;
$smPlanOwnLock = cb_smeny_planovani_zamek_vlastni($smPlanLock,$smPlanBranchId,$smPlanPerson);
$smPlanCanEdit = cb_smeny_planovani_edituje() && $smPlanOwnLock && $smPlanCanStart;
$smPlanCanPublish = cb_smeny_planovani_publikuje() && $smPlanOwnLock && $smPlanCanStart;
$smPlanFlash = cb_smeny_sablony_flash_nacist();
$smPlanUrl = cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$smPlanBranchId.'&week='.$smPlanWeekIndex);
$smPlanCandidates = [];
if ($smPlan !== null && $smPlanCanEdit && $smPlan['stav'] === 'rozpracovany') {
    foreach ($smPlanWeek['days'] as $day) foreach (cb_smeny_sablony_pozice($smPlanBranch) as $idSlot=>$label) {
        $smPlanCandidates[$day['date'].':'.$idSlot] = cb_smeny_planovani_kandidati($smDb,$smPlan,$day['date'],(int)$idSlot);
    }
}
// Každý formulář váže akci na konkrétní týden, relaci a načtenou verzi rozpisu.
$smPlanFields = static function () use ($smPlanBranchId,$smPlanWeekIndex,$smPlanWeek,$smPlan,$smPlanOwnLock,$smPlanLock): void {
    $fields = ['cb_crf'=>cb_crf_token(),'id_pob'=>$smPlanBranchId,'week'=>$smPlanWeekIndex,'tyden_od'=>$smPlanWeek['start_day'],
        'zamek_token'=>$smPlanOwnLock ? $smPlanLock['token'] : '', 'id_smeny_rozpis'=>$smPlan['id_smeny_rozpis'] ?? 0,
        'verze'=>$smPlan['verze'] ?? 0,'stav'=>$smPlan['stav'] ?? ''];
    foreach ($fields as $key=>$value) echo '<input type="hidden" name="'.h($key).'" value="'.h((string)$value).'">';
};
?>
<section class="pp smeny_content smeny_planning" data-module="smeny" data-page="planovani_smen">
  <header class="pp_header"><h1>Plánování směn</h1></header>
  <form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="smeny_planning_filters">
    <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="planovani_smen">
    <label>Pobočka<select name="id_pob" onchange="this.form.submit()"><?php foreach($smPlanBranches as $row): ?><option value="<?= (int)$row['id_pob'] ?>"<?= (int)$row['id_pob']===$smPlanBranchId?' selected':'' ?>><?= h($row['nazev']) ?></option><?php endforeach; ?></select></label>
    <label>Týden<select name="week" onchange="this.form.submit()"><?php foreach($smPlanWeeks as $index=>$week): ?><option value="<?= $index ?>"<?= $index===$smPlanWeekIndex?' selected':'' ?>><?= h($week['start']->format('j. n.').'–'.$week['end']->format('j. n. Y').($index===0?' – aktuální týden':'').($smPlanNow<$week['deadline']?' – požadavky otevřené':'')) ?></option><?php endforeach; ?></select></label>
  </form>
  <?php if($smPlanFlash): ?><p class="smeny_notice smeny_notice--<?= $smPlanFlash['type']==='success'?'success':'error' ?>"><?= h($smPlanFlash['text']) ?></p><?php endif; ?>
  <?php if(!$smPlanBranch): ?><p class="smeny_notice smeny_notice--error">Nemáte dostupnou pobočku pro plánování.</p>
  <?php else: ?>
    <?php if(!$smPlanCanStart): ?><p class="smeny_notice">Plánování se otevře po uzávěrce požadavků <?= h($smPlanWeek['deadline']->format('j. n. Y \v H:i')) ?>.</p><?php endif; ?>
    <?php if(cb_smeny_planovani_edituje() || cb_smeny_planovani_publikuje()): ?>
      <div class="smeny_planning_lock">
      <?php if($smPlanOwnLock): ?>
        <p>Pobočku plánujete vy. Zámek platí do <strong><?= h(substr($smPlanLock['platny_do'],11,5)) ?></strong>. Uložení ho prodlouží o 15 minut.</p>
        <form method="post" action="<?= h($smPlanUrl) ?>"><?php $smPlanFields(); ?>
          <?php if($smPlanCanStart): ?><button name="action" value="smeny_plan_obnovit_zamek" class="smeny_secondary_button">Prodloužit zámek</button><?php endif; ?>
          <button name="action" value="smeny_plan_uvolnit" class="smeny_secondary_button">Uvolnit pobočku</button>
        </form>
      <?php elseif($smPlanLock !== null): ?>
        <p class="smeny_notice">Pobočku právě plánuje jiný uživatel. Rozpis je pouze pro čtení. Zámek platí do <?= h(substr($smPlanLock['platny_do'],11,5)) ?>.</p>
      <?php elseif($smPlanCanStart): ?>
        <form method="post" action="<?= h($smPlanUrl) ?>"><?php $smPlanFields(); ?><button name="action" value="smeny_plan_zamek" class="smeny_primary_button">Převzít plánování pobočky</button></form>
      <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if($smPlan === null): ?>
      <div class="smeny_planning_empty"><p>Pro tento týden zatím není založený interní rozpis.</p>
      <?php if($smPlanCanEdit): ?><form method="post" action="<?= h($smPlanUrl) ?>"><?php $smPlanFields(); ?>
        <label>Výchozí šablona<select name="id_smeny_sablona"><option value="0">Bez šablony</option><?php foreach($smPlanTemplates as $template): ?><option value="<?= (int)$template['id_smeny_sablona'] ?>"><?= h($template['nazev']) ?></option><?php endforeach; ?></select></label>
        <button name="action" value="smeny_plan_zalozit" class="smeny_primary_button">Založit pracovní rozpis</button>
      </form><?php endif; ?></div>
    <?php else: ?>
      <div class="smeny_planning_meta"><strong><?= h(['rozpracovany'=>'Rozpracováno','pripraveny'=>'Připraveno ke zveřejnění','zverejneny'=>'Zveřejněno'][$smPlan['stav']]) ?></strong><span>Šablona: <?= h($smPlan['sablona_nazev'] ?? 'bez šablony') ?></span></div>
      <?php if($smPlan['pracovni_verze'] !== null): ?><p class="smeny_notice"><?= $smPlan['zverejnena_verze'] !== null ? 'Upravujete pracovní kopii. Zaměstnanci nadále vidí poslední zveřejněné směny.' : 'Pracovní rozpis ještě není zveřejněný.' ?></p><?php endif; ?>
      <?php if($smPlanCanEdit && $smPlan['stav']==='rozpracovany' && $smPlan['zverejnena_verze']===null && $smPlan['blocks']===[] && $smPlanTemplates!==[]): ?>
        <form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_planning_template_reload"><?php $smPlanFields(); ?>
          <label>Šablona<select name="id_smeny_sablona"><?php foreach($smPlanTemplates as $template): ?><option value="<?= (int)$template['id_smeny_sablona'] ?>"><?= h($template['nazev']) ?></option><?php endforeach; ?></select></label>
          <button name="action" value="smeny_plan_sablona_obnovit" class="smeny_secondary_button">Načíst vybranou šablonu</button><small>Pozdější změny šablony tento týden neovlivní.</small>
        </form>
      <?php endif; ?>
      <?php if($smPlanCanEdit && $smPlan['stav']==='rozpracovany'): ?>
        <p class="smeny_planning_help"><strong>Jak plánovat:</strong> Klikněte na začátek, vyberte zaměstnance a klikněte na konec ve stejném řádku. Barevné pole je doporučení šablony; lze ho rozdělit mezi více lidí nebo naplánovat směny mimo něj.</p>
        <div class="smeny_planner" data-smeny-planner>
          <div class="smeny_planner_days">
          <?php foreach($smPlanWeek['days'] as $dayIndex=>$day): $dayNumber=$dayIndex+1; $closing=cb_smeny_sablony_zaviraci_cas($smPlanBranch,$dayNumber); ?>
            <article class="smeny_planner_day"><header><strong><?= h($day['name'].' '.$day['date_label']) ?></strong><small>zavírá <?= h($closing ?: 'neuvedeno') ?></small></header>
            <?php foreach(cb_smeny_sablony_pozice($smPlanBranch) as $idSlot=>$slotName):
              $guides=array_values(array_filter($smPlan['potreba'],static fn(array $b):bool=>(int)$b['den_tydne']===$dayNumber && (int)$b['id_slot']===(int)$idSlot));
              $assignments=array_values(array_filter($smPlan['blocks'],static fn(array $b):bool=>$b['datum']===$day['date'] && (int)$b['id_slot']===(int)$idSlot));
            ?>
              <div class="smeny_planner_row" data-smeny-row data-date="<?= h($day['date']) ?>" data-slot="<?= (int)$idSlot ?>" data-slot-name="<?= h($slotName) ?>">
                <div class="smeny_planner_row_title"><strong><?= h($slotName) ?></strong>
                <?php foreach($assignments as $assigned): ?><form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_planner_assignment"><?php $smPlanFields(); ?><input type="hidden" name="id_smeny_smena" value="<?= (int)$assigned['id_smeny_smena'] ?>"><span><?= h($assigned['cas_od'].'–'.$assigned['cas_do'].' '.$assigned['pracovnik']) ?></span><button name="action" value="smeny_plan_odebrat" class="smeny_secondary_button" aria-label="<?= h('Odebrat směnu '.$assigned['pracovnik'].' '.$assigned['cas_od'].'–'.$assigned['cas_do']) ?>">Odebrat</button></form><?php endforeach; ?>
                <?php foreach($assignments as $assigned): ?><details><summary><?= h('Upravit čas · '.$assigned['pracovnik']) ?></summary><form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_shift_edit"><?php $smPlanFields(); ?>
                  <input type="hidden" name="id_smeny_smena" value="<?= (int)$assigned['id_smeny_smena'] ?>"><input type="hidden" name="id_person" value="<?= (int)$assigned['id_person'] ?>"><input type="hidden" name="datum" value="<?= h($day['date']) ?>"><input type="hidden" name="id_slot" value="<?= (int)$idSlot ?>">
                  <label>Od<input type="time" name="cas_od" step="1800" value="<?= h($assigned['cas_od']) ?>" required></label><label>Do<input type="time" name="cas_do" step="60" value="<?= h($assigned['cas_do']) ?>" required></label><label><input type="checkbox" name="bez_ohledu" value="1"<?= (int)$assigned['bez_ohledu_na_pozadavky']===1?' checked':'' ?>> Bez ohledu na požadavky</label><button name="action" value="smeny_plan_zmenit" class="smeny_secondary_button">Uložit čas</button>
                </form></details><?php endforeach; ?>
                </div>
                <div class="smeny_planner_timeline">
                <?php if($closing !== ''): foreach(cb_smeny_sablony_casy($closing,true) as $time): $minute=cb_smeny_sablony_cas_minuty($time,true); if($minute<600)continue; $guideCount=0; foreach($guides as $guide){if($minute>=cb_smeny_sablony_cas_minuty(substr($guide['cas_od'],0,5),false) && $minute<cb_smeny_sablony_cas_minuty(substr($guide['cas_do'],0,5),true))$guideCount++;} ?>
                  <button type="button" class="smeny_planner_tick<?= $guideCount>0?' is-guide':'' ?>" data-smeny-time="<?= h($time) ?>" title="<?= h($time.($guideCount>0?' · šablona '.$guideCount.'×':'')) ?>"><?php if($guideCount>1): ?><em><?= $guideCount ?></em><?php endif; ?><span><?= h($time) ?></span></button>
                <?php endforeach; endif; ?>
                </div>
              </div>
            <?php endforeach; ?></article>
          <?php endforeach; ?>
          </div>
          <aside class="smeny_planner_people" data-smeny-people hidden>
            <header><strong data-smeny-selection-title>Vyberte zaměstnance</strong><button type="button" data-smeny-cancel aria-label="Zrušit výběr">×</button></header>
            <label class="smeny_planner_ignore"><input type="checkbox" data-smeny-ignore> Bez ohledu na požadavky</label>
            <p data-smeny-people-help>Nejprve jsou lidé s hlavní pobočkou zde, potom pracovníci z ostatních poboček.</p><div data-smeny-people-list></div>
          </aside>
          <form method="post" action="<?= h($smPlanUrl) ?>" data-smeny-assignment-form hidden><?php $smPlanFields(); ?>
            <input type="hidden" name="action" value="smeny_plan_priradit"><input type="hidden" name="datum"><input type="hidden" name="id_slot"><input type="hidden" name="cas_od"><input type="hidden" name="cas_do"><input type="hidden" name="id_person"><input type="hidden" name="bez_ohledu" value="0">
          </form>
          <script type="application/json" data-smeny-candidates><?= json_encode($smPlanCandidates,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_THROW_ON_ERROR) ?></script>
        </div>
      <?php else: ?>
        <div class="smeny_planning_days"><?php foreach($smPlanWeek['days'] as $day): ?><article><strong><?= h($day['name'].' '.$day['date_label']) ?></strong><?php $found=false; foreach($smPlan['blocks'] as $block){if($block['datum']!==$day['date'])continue;$found=true; ?><div class="smeny_planning_block"><span><?= h($block['cas_od'].'–'.$block['cas_do']) ?></span><span><?= h(cb_smeny_sablony_nazev_pozice($smPlanBranch,(int)$block['id_slot'])) ?></span><strong><?= h($block['pracovnik']) ?></strong></div><?php } if(!$found): ?><small>Bez naplánovaných směn</small><?php endif; ?></article><?php endforeach; ?></div>
      <?php endif; ?>
      <?php require __DIR__.'/zruseni_planovani.php'; ?>
      <?php if($smPlanCanEdit || $smPlanCanPublish): ?><form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_planning_actions"><?php $smPlanFields(); ?>
        <?php if($smPlanCanEdit): ?>
          <?php if($smPlan['stav']==='rozpracovany'): ?><button name="action" value="smeny_plan_pripraven" class="smeny_primary_button">Týden je připravený ke zveřejnění</button>
          <?php else: ?><button name="action" value="smeny_plan_upravit" class="smeny_secondary_button"><?= $smPlan['zverejnena_verze']!==null?'Upravit pracovní kopii':'Ještě to upravím' ?></button><?php endif; ?>
        <?php endif; ?>
        <?php if($smPlan['stav']==='pripraveny' && $smPlanCanPublish): ?><button name="action" value="smeny_plan_zverejnit" class="smeny_primary_button"><?= $smPlan['zverejnena_verze']===null?'Zveřejnit směny':'Zveřejnit změny' ?></button><?php endif; ?>
      </form><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
