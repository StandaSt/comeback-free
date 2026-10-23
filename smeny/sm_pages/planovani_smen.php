<?php
declare(strict_types=1);
/* Účel: Plánovač přiřazuje směny a jedním dokončením zveřejní verzovaný rozpis; zobrazí jeho schválení a umožní oprávněné pozdější úpravy. */
$smPlanBranchId = (int)($_GET['id_pob'] ?? array_key_first($smPlanBranches) ?? 0);
$smPlanWeekIndex = max(0,min(count($smPlanWeeks)-1,(int)($_GET['week'] ?? 1)));
$smPlanWeek = $smPlanWeeks[$smPlanWeekIndex];
$smPlanBranch = $smPlanBranches[$smPlanBranchId] ?? null;
$smPlanNow = new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
$smPlan = $smPlanBranch ? cb_smeny_planovani_nacist($smDb,$smPlanBranchId,$smPlanWeek['start_day']) : null;
$smPlanSchemaReady=cb_smeny_planovani_schema($smDb);
// Obě nastavení patří pobočce a týdnu, nikoli účtu nebo přihlášené relaci.
$smPlanAnytime = !empty($smPlan['planovat_kdykoliv']);
$smPlanAllBranches = !empty($smPlan['bez_ohledu_na_pobocku']);
$smPlanCanStart = $smPlanAnytime || $smPlanNow >= $smPlanWeek['deadline'];
$smPlanTemplates = $smPlanBranch ? cb_smeny_sablony_seznam($smDb,[$smPlanBranchId=>$smPlanBranch]) : [];
$smPlanPerson = cb_smeny_sablony_id_person($smDb);
$smPlanLock = $smPlanBranch ? cb_smeny_planovani_zamek_nacist($smDb,$smPlanBranchId) : null;
$smPlanOwnLock = cb_smeny_planovani_zamek_vlastni($smPlanLock,$smPlanBranchId,$smPlanPerson);
$smPlanCanEdit = $smPlanSchemaReady && cb_smeny_planovani_edituje() && $smPlanOwnLock && $smPlanCanStart;
$smPlanApprovalReady=cb_smeny_souhlasy_schema($smDb);
$smPlanCanPublish = $smPlanCanEdit && $smPlanApprovalReady;
$smPlanFlash = cb_smeny_sablony_flash_nacist();
$smPlanUrl = cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$smPlanBranchId.'&week='.$smPlanWeekIndex);
$smPlanCandidates = [];
$smPlanBusy = [];
$smPlanHours = $smPlan !== null ? cb_smeny_planovani_tydenni_hodiny($smDb,$smPlan) : [];
if ($smPlan !== null && $smPlanCanEdit && $smPlan['stav'] === 'rozpracovany') {
    foreach ($smPlanWeek['days'] as $day) foreach (cb_smeny_sablony_pozice($smPlanBranch) as $idSlot=>$label) {
        $smPlanCandidates[$day['date'].':'.$idSlot] = cb_smeny_planovani_kandidati($smDb,$smPlan,$day['date'],(int)$idSlot);
    }
    // Obsazení pro všechny pozice se načte najednou; prohlížeč je filtruje při změně Od–Do.
    $candidateIds = [];
    foreach ($smPlanCandidates as $candidates) foreach ($candidates as $candidate) $candidateIds[] = $candidate['id_person'];
    $smPlanBusy = cb_smeny_planovani_obsazene_intervaly($smDb,$smPlan,$candidateIds);
}
// Každý formulář váže akci na konkrétní týden, relaci a načtenou verzi rozpisu.
$smPlanFields = static function () use ($smPlanBranchId,$smPlanWeekIndex,$smPlanWeek,$smPlan,$smPlanOwnLock,$smPlanLock): void {
    $fields = ['cb_crf'=>cb_crf_token(),'id_pob'=>$smPlanBranchId,'week'=>$smPlanWeekIndex,'den_index'=>max(0,min(6,(int)($_GET['den_index'] ?? 0))),'tyden_od'=>$smPlanWeek['start_day'],
        'zamek_token'=>$smPlanOwnLock ? $smPlanLock['token'] : '', 'id_smeny_rozpis'=>$smPlan['id_smeny_rozpis'] ?? 0,
        'verze'=>$smPlan['verze'] ?? 0,'stav'=>$smPlan['stav'] ?? '',
        'nastaveni_otisk'=>hash('sha256',(int)!empty($smPlan['planovat_kdykoliv']).':'.(int)!empty($smPlan['bez_ohledu_na_pobocku']))];
    foreach ($fields as $key=>$value) echo '<input type="hidden" name="'.h($key).'" value="'.h((string)$value).'">';
};
?>
<section class="pp smeny_content <?= cb_smeny_styl_trida($smStyl) ?> smeny_planning" data-smeny-view-pending data-module="smeny" data-selected-day="<?= max(0,min(6,(int)($_GET['den_index'] ?? 0))) ?>" data-page="planovani_smen"<?= $smPlanOwnLock?' data-smeny-lock':'' ?> data-lock-remaining="<?= $smPlanOwnLock?(int)$smPlanLock['zbyva_sekund']:0 ?>" data-lock-branch="<?= $smPlanBranchId ?>" data-lock-overview="<?= h(cb_root_url('index.php?m=smeny&page=prehled')) ?>">
  <?php // Bez JavaScriptu zůstává serverový pohled čitelný; čekání se týká jen jeho klientské přestavby. ?>
  <noscript><style>.smeny_content[data-smeny-view-pending]{visibility:visible}</style></noscript>
  <?php if($smPlanOwnLock): ?><form method="post" action="<?= h($smPlanUrl) ?>" data-smeny-lock-form hidden><?php $smPlanFields(); ?></form><?php endif; ?>
  <div class="smeny_planning_controls">
  <header class="pp_header"><h1>Plánování směn</h1>
    <?php if(!$smPlanOwnLock && $smPlanLock===null && $smPlanSchemaReady && $smPlanBranch && $smPlanCanStart && (cb_smeny_planovani_edituje() || cb_smeny_planovani_publikuje())): ?>
      <form method="post" action="<?= h($smPlanUrl) ?>"><?php $smPlanFields(); ?><button name="action" value="smeny_plan_zamek" class="smeny_primary_button">Plánovat zvolený týden</button></form>
    <?php endif; ?>
  </header>
  <div class="smeny_planning_top">
  <form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="smeny_planning_filters">
    <input type="hidden" name="m" value="smeny"><input type="hidden" name="page" value="planovani_smen">
    <label>Pobočka<select name="id_pob" onchange="this.form.requestSubmit()"><?php foreach($smPlanBranches as $row): ?><option value="<?= (int)$row['id_pob'] ?>"<?= (int)$row['id_pob']===$smPlanBranchId?' selected':'' ?>><?= h($row['nazev']) ?></option><?php endforeach; ?></select></label>
    <label>Týden<select name="week" onchange="this.form.requestSubmit()"><?php foreach($smPlanWeeks as $index=>$week): ?><option value="<?= $index ?>"<?= $index===$smPlanWeekIndex?' selected':'' ?>><?= h($week['start']->format('j. n.').'–'.$week['end']->format('j. n. Y').($index===0?' – aktuální týden':'').($smPlanNow<$week['deadline']?' – požadavky otevřené':'')) ?></option><?php endforeach; ?></select></label>
  </form>
  <?php if($smPlanBranch): ?>
  <form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_planning_settings"><?php $smPlanFields(); ?>
    <input type="hidden" name="action" value="smeny_plan_nastaveni">
    <fieldset<?= !$smPlanSchemaReady || !cb_smeny_planovani_edituje() || ($smPlanLock!==null && !$smPlanOwnLock) ? ' disabled':'' ?>>
      <label><input type="checkbox" name="planovat_kdykoliv" value="1"<?= $smPlanAnytime?' checked':'' ?> onchange="this.form.requestSubmit()"> Plánovat kdykoliv</label>
      <label><input type="checkbox" name="bez_ohledu_na_pobocku" value="1"<?= $smPlanAllBranches?' checked':'' ?> onchange="this.form.requestSubmit()"> Bez ohledu na oblast</label>
    </fieldset>
  </form>
  <?php endif; ?>
  <?php if(!$smPlanOwnLock && $smPlanLock!==null): ?><div class="smeny_planning_lock">
    <p>Plánování směn je nyní používáno uživatelem <?= h($smPlanLock['jmeno']) ?>.</p>
  </div><?php endif; ?>
  </div>
  </div>
  <?php if(!$smPlanSchemaReady): ?><p class="smeny_notice smeny_notice--error">Plánování čeká na aktualizaci databáze správcem. Stávající rozpis je zatím pouze ke čtení.</p><?php endif; ?>
  <?php if($smPlanFlash): ?><p class="smeny_notice smeny_notice--<?= $smPlanFlash['type']==='success'?'success':'error' ?>"><?= h($smPlanFlash['text']) ?></p><?php endif; ?>
  <?php if($smPlan!==null && $smPlan['zverejnena_verze']!==null): ?><p class="smeny_notice">Zveřejněná verze <?= (int)$smPlan['zverejnena_verze'] ?> · <?= !empty($smPlan['definitivni_od'])?'Definitivně platný rozpis':'Čeká na souhlasy zaměstnanců' ?></p><?php endif; ?>
  <?php if(!$smPlanApprovalReady): ?><p class="smeny_notice">Zveřejnění a souhlasy čekají na aktualizaci databáze.</p><?php endif; ?>
  <?php if(!$smPlanBranch): ?><p class="smeny_notice smeny_notice--error">Nemáte dostupnou pobočku pro plánování.</p>
  <?php else: ?>
    <?php if($smPlan === null): ?>
      <div class="smeny_planning_empty"><p>Pro tento týden zatím není založený interní rozpis.</p>
      <?php if($smPlanCanEdit): ?><form method="post" action="<?= h($smPlanUrl) ?>"><?php $smPlanFields(); ?>
        <label>Výchozí šablona<select name="id_smeny_sablona"><option value="0">Bez šablony</option><?php foreach($smPlanTemplates as $template): ?><option value="<?= (int)$template['id_smeny_sablona'] ?>"><?= h($template['nazev']) ?></option><?php endforeach; ?></select></label>
        <button name="action" value="smeny_plan_zalozit" class="smeny_primary_button">Založit pracovní rozpis</button>
      </form><?php endif; ?></div>
    <?php else: ?>
      <div class="smeny_planning_day_toolbar">
        <?php if(($smPlanCanEdit && $smPlan['stav']==='rozpracovany') || $smStyl===1): ?>
          <label class="smeny_template_resolution" data-smeny-planning-resolution><input type="checkbox" data-smeny-half-hours> po 30 min.</label>
        <?php endif; ?>
        <form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_planning_template_select"><?php $smPlanFields(); ?>
          <input type="hidden" name="action" value="smeny_plan_sablona_obnovit">
          <?php // Název je uloženým otiskem podkladu; smazanou šablonu nepřepínáme samovolně na jinou. ?>
          <?php $smCurrentTemplateFound=$smPlan['sablona_nazev']===null || in_array($smPlan['sablona_nazev'],array_column($smPlanTemplates,'nazev'),true); ?>
          <label>Šablona:
            <select name="id_smeny_sablona" aria-label="Šablona plánování" onchange="this.form.requestSubmit()"<?= !$smPlanCanEdit || $smPlan['stav']!=='rozpracovany'?' disabled':'' ?><?= !$smPlanCanEdit?' title="Nejprve zahajte plánování zvoleného týdne."':'' ?>>
              <?php if(!$smCurrentTemplateFound): ?><option value="" selected disabled><?= h($smPlan['sablona_nazev']) ?></option><?php endif; ?>
              <option value="0"<?= $smPlan['sablona_nazev']===null?' selected':'' ?>>Bez šablony</option>
              <?php foreach($smPlanTemplates as $template): ?><option value="<?= (int)$template['id_smeny_sablona'] ?>"<?= $template['nazev']===$smPlan['sablona_nazev']?' selected':'' ?>><?= h($template['nazev']) ?></option><?php endforeach; ?>
            </select>
          </label>
        </form>
      </div>
      <?php if(($smPlanCanEdit && $smPlan['stav']==='rozpracovany') || $smStyl===1): ?>
        <div class="smeny_planner" data-current-branch="<?= $smPlanBranchId ?>" data-smeny-planner data-can-edit="<?= $smPlanCanEdit && $smPlan['stav']==='rozpracovany'?'1':'0' ?>" data-all-branches="<?= $smPlanAllBranches?'1':'0' ?>">
          <div class="smeny_planner_days">
          <?php foreach($smPlanWeek['days'] as $dayIndex=>$day): $dayNumber=$dayIndex+1; $closing=cb_smeny_sablony_zaviraci_cas($smPlanBranch,$dayNumber); ?>
            <article class="smeny_planner_day" data-smeny-grid-day data-date="<?= h($day['date']) ?>" data-positions="<?= h(json_encode(cb_smeny_sablony_pozice($smPlanBranch),JSON_THROW_ON_ERROR)) ?>" data-base-positions="<?= h(json_encode(cb_smeny_sloty_zakladni_id($smPlanBranch),JSON_THROW_ON_ERROR)) ?>" data-day-label="<?= h($day['name'].' '.$day['date_label']) ?>" data-closing="<?= h($closing) ?>"><header><strong><?= h($day['name'].' '.$day['date_label']) ?></strong><small>zavírá <?= h($closing ?: 'neuvedeno') ?></small></header>
            <?php foreach(cb_smeny_sablony_pozice($smPlanBranch) as $idSlot=>$slotName):
              $guides=array_values(array_filter($smPlan['potreba'],static fn(array $b):bool=>(int)$b['den_tydne']===$dayNumber && (int)$b['id_slot']===(int)$idSlot));
              $columns=array_values(array_filter($smPlan['sloty'],static fn(array $column):bool=>$column['datum']===$day['date'] && (int)$column['id_slot']===(int)$idSlot));
              foreach($columns as $columnIndex=>$column):
              $columnKey=$column['klic_slotu'];
              $guideIndex=(int)$column['poradi']-1;
              $columnGuides=isset($guides[$guideIndex])?[$guides[$guideIndex]]:[];
              $assignments=array_values(array_filter($smPlan['blocks'],static fn(array $b):bool=>($b['klic_slotu'] ?? '')===$columnKey));
            ?>
              <div class="smeny_planner_row" data-smeny-row data-date="<?= h($day['date']) ?>" data-slot="<?= (int)$idSlot ?>" data-column="<?= h($columnKey) ?>" data-slot-name="<?= h($slotName.' '.($columnIndex+1)) ?>" data-guides="<?= h(json_encode($columnGuides,JSON_THROW_ON_ERROR)) ?>">
                <div class="smeny_planner_row_title"><strong><?= h($slotName) ?></strong>
                <?php if($smPlanCanEdit && $smPlan['stav']==='rozpracovany' && (count($columns)>1 || !in_array((int)$idSlot,cb_smeny_sloty_zakladni_id($smPlanBranch),true))): ?>
                <form method="post" action="<?= h($smPlanUrl) ?>" data-smeny-column-remove data-occupied="<?= count($assignments) ?>"><?php $smPlanFields(); ?>
                  <input type="hidden" name="action" value="smeny_plan_slot_odebrat"><input type="hidden" name="datum" value="<?= h($day['date']) ?>"><input type="hidden" name="id_slot" value="<?= (int)$idSlot ?>"><input type="hidden" name="klic_slotu" value="<?= h($columnKey) ?>"><input type="hidden" name="potvrzeno" value="0"><input type="hidden" name="obsazeni_otisk" value="<?= h(cb_smeny_planovani_slot_obsazeni_otisk($assignments)) ?>">
                  <button type="submit" aria-label="Odstranit slot <?= h($slotName.' '.($columnIndex+1)) ?>">×</button>
                </form>
                <?php endif; ?>
                <?php foreach($assignments as $assigned): ?>
                  <article class="smeny_planner_assigned" data-smeny-assigned data-shift-id="<?= (int)$assigned['id_smeny_smena'] ?>" data-assigned-person="<?= (int)$assigned['id_person'] ?>" data-from="<?= h($assigned['cas_od']) ?>" data-to="<?= h($assigned['cas_do']) ?>" data-name="<?= h($assigned['pracovnik']) ?>" data-surname="<?= h($assigned['pracovnik_prijmeni'] ?? '') ?>" data-given-name="<?= h($assigned['pracovnik_jmeno'] ?? '') ?>">
                    <strong><?= h($assigned['cas_od'].'–'.$assigned['cas_do'].' '.$assigned['pracovnik']) ?></strong>
                    <?php if($smPlanCanEdit && $smPlan['stav']==='rozpracovany'): ?>
                    <details><summary>Upravit směnu</summary>
                      <form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_shift_edit"><?php $smPlanFields(); ?>
                        <input type="hidden" name="id_smeny_smena" value="<?= (int)$assigned['id_smeny_smena'] ?>"><input type="hidden" name="id_person" value="<?= (int)$assigned['id_person'] ?>"><input type="hidden" name="datum" value="<?= h($day['date']) ?>"><input type="hidden" name="id_slot" value="<?= (int)$idSlot ?>">
                        <input type="hidden" name="klic_slotu" value="<?= h($columnKey) ?>">
                        <label>Od<input type="time" name="cas_od" step="1800" value="<?= h($assigned['cas_od']) ?>" required></label>
                        <label>Do<input type="time" name="cas_do" step="60" value="<?= h($assigned['cas_do']) ?>" required></label>
                        <button name="action" value="smeny_plan_zmenit" class="smeny_primary_button">Uložit čas</button>
                        <button name="action" value="smeny_plan_odebrat" class="smeny_secondary_button">Odebrat směnu</button>
                      </form>
                    </details>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
                </div>
                <div class="smeny_planner_timeline">
                <?php if($closing !== ''): foreach(cb_smeny_sablony_casy($closing,true) as $time): $minute=cb_smeny_sablony_cas_minuty($time,true); if($minute<600)continue; $guideCount=0; foreach($guides as $guide){if($minute>=cb_smeny_sablony_cas_minuty(substr($guide['cas_od'],0,5),false) && $minute<cb_smeny_sablony_cas_minuty(substr($guide['cas_do'],0,5),true))$guideCount++;} ?>
                  <button type="button" class="smeny_planner_tick<?= $guideCount>0?' is-guide':'' ?>" data-smeny-time="<?= h($time) ?>" data-smeny-default-end="<?= h($closing) ?>"<?= !$smPlanCanEdit || $smPlan['stav']!=='rozpracovany'?' disabled':'' ?> title="<?= h($time.($guideCount>0?' · šablona '.$guideCount.'×':'')) ?>"><?php if($guideCount>1): ?><em><?= $guideCount ?></em><?php endif; ?><span><?= h($time) ?></span></button>
                <?php endforeach; endif; ?>
                </div>
              </div>
            <?php endforeach; endforeach; ?></article>
          <?php endforeach; ?>
          </div>
          <aside class="smeny_planner_people" data-smeny-people hidden>
            <header><strong data-smeny-selection-title>Vyberte zaměstnance</strong><button type="button" data-smeny-cancel aria-label="Zrušit výběr">×</button></header>
            <div class="smeny_classic_assignment" data-smeny-choice hidden>
              <label>Od<input type="time" step="1800" data-smeny-choice-from required></label>
              <label>Do<input type="time" step="60" data-smeny-choice-to required></label>
            </div>
            <div data-smeny-people-list></div>
          </aside>
          <form method="post" action="<?= h($smPlanUrl) ?>" data-smeny-assignment-form hidden><?php $smPlanFields(); ?>
            <input type="hidden" name="action" value="smeny_plan_priradit"><input type="hidden" name="datum"><input type="hidden" name="id_slot"><input type="hidden" name="klic_slotu"><input type="hidden" name="cas_od"><input type="hidden" name="cas_do"><input type="hidden" name="id_person">
          </form>
          <form method="post" action="<?= h($smPlanUrl) ?>" data-smeny-column-add hidden><?php $smPlanFields(); ?><input type="hidden" name="action" value="smeny_plan_slot_pridat"><input type="hidden" name="datum"><input type="hidden" name="id_slot"></form>
          <script type="application/json" data-smeny-candidates><?= json_encode($smPlanCandidates,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_THROW_ON_ERROR) ?></script>
          <script type="application/json" data-smeny-week-hours><?= json_encode($smPlanHours,JSON_THROW_ON_ERROR) ?></script>
          <script type="application/json" data-smeny-busy><?= json_encode($smPlanBusy,JSON_THROW_ON_ERROR) ?></script>
        </div>
      <?php else: ?>
        <div class="smeny_planning_days"><?php foreach($smPlanWeek['days'] as $day): ?><article><strong><?= h($day['name'].' '.$day['date_label']) ?></strong><?php $found=false; foreach($smPlan['blocks'] as $block){if($block['datum']!==$day['date'])continue;$found=true; ?><div class="smeny_planning_block"><span><?= h($block['cas_od'].'–'.$block['cas_do']) ?></span><span><?= h(cb_smeny_sablony_nazev_pozice($smPlanBranch,(int)$block['id_slot'])) ?></span><strong><?= h($block['pracovnik']) ?></strong></div><?php } if(!$found): ?><small>Bez naplánovaných směn</small><?php endif; ?></article><?php endforeach; ?></div>
      <?php endif; ?>
      <?php if($smPlanCanEdit || $smPlanCanPublish): ?><form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_planning_actions"><?php $smPlanFields(); ?>
        <?php if($smPlanCanEdit): ?>
          <?php if($smPlan['stav']==='rozpracovany'): ?><button name="action" value="smeny_plan_pripraven" class="smeny_primary_button"<?= !$smPlanCanPublish?' disabled':'' ?>><?= $smPlan['zverejnena_verze']===null?'Týden je připravený ke zveřejnění':'Zveřejnit změny' ?></button>
          <?php else: ?><button name="action" value="smeny_plan_upravit" class="smeny_secondary_button"><?= $smPlan['zverejnena_verze']!==null?'Upravit pracovní kopii':'Ještě to upravím' ?></button><?php endif; ?>
        <?php endif; ?>
        <?php if($smPlan['stav']==='pripraveny' && $smPlanCanPublish): ?><button name="action" value="smeny_plan_zverejnit" class="smeny_primary_button"><?= $smPlan['zverejnena_verze']===null?'Zveřejnit směny':'Zveřejnit změny' ?></button><?php endif; ?>
      </form><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
