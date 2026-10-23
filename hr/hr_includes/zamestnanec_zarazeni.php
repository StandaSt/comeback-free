<?php
declare(strict_types=1);
/* Ucel: Sdileny blok slotu a provozni funkce osoby a samostatny blok skutecne manazerske pozice. */
$isManager=($currentPersonFunction['zarazeni']??'')==='manazerske';
$isAdmin=hr_ciselnik_je_admin($db);
$personId=(int)$employee['id_person'];
$url=hr_pracovni_pomer_url($personId);
// Obe skupiny maji stejny ciselnik i historii; typ funkce se uklada osobe, nikoli slotu.
foreach (['provozni'=>'Pracovní sloty a funkce','manazerske'=>'Manažerská pozice'] as $kind=>$title):
    $operational=$kind==='provozni';
    $key=$operational?'slot':'function';
    $formId=$operational?'hr-slot-form':'hr-function-form';
    $canEdit=$isEdit && cb_pravo_ma(307) && (!$operational || !$isManager)
        && ($operational || $isAdmin || (cb_pravo_ma(316) && (int)($currentPersonFunction['pouze_admin']??0)!==1));
    $selected=($currentPersonFunction['zarazeni']??'')===$kind?(int)$currentPersonFunction['id_funkce']:0;
    $history=array_values(array_filter($functionData['historie'],static fn($r)=>$r['zarazeni']===$kind));
?>
<section class="hr_panel" data-hr-employee-block="<?= h($key) ?>">
    <div class="hr_panel_header"><h2 class="hr_panel_title"><?= h($title) ?></h2><div class="hr_employee_block_actions">
        <?php if ($canEdit): ?><button class="hr_primary_button" type="submit" form="<?= h($formId) ?>" data-hr-employee-save>Uložit změny</button><?php endif; ?>
        <?php if (cb_hr_nastaveni_ma_pravo()): ?>
        <?php if ($operational): ?><a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=nastaveni&navrat_person='.$personId.'#hr-settings-slot')) ?>">Spravovat sloty</a><?php endif; ?>
        <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=nastaveni&navrat_person='.$personId.'#hr-settings-funkce')) ?>"><?= $operational?'Spravovat funkce':'Spravovat pozice' ?></a>
        <?php endif; ?>
    </div></div>
    <?php if ($operational && $isManager): ?><p class="hr_employee_compact_note">Manažerská pozice nemá pracovní sloty ani provozní funkci.</p><?php else: ?>
    <form id="<?= h($formId) ?>" data-hr-employee-form="<?= h($key) ?>" class="hr_form hr_assignment_form"<?= $canEdit?' method="post" action="'.h($url).'"':'' ?>>
        <?php if ($canEdit): ?><input type="hidden" name="cb_action" value="<?= $operational?'hr_zamestnanec_pozice_zmenit':'hr_zamestnanec_funkce_zmenit' ?>"><input type="hidden" name="id_person" value="<?= $personId ?>"><?php endif; ?>
        <fieldset class="hr_employee_mode_fields hr_assignment_fields"<?= $canEdit?'':' disabled' ?>>
        <?php if ($operational): ?><label class="hr_form_label"><span class="hr_form_label_text">Přidat slot</span><select name="id_slot"><option value="">Bez přidání slotu</option><?php foreach ($positionData['pozice'] as $slot): ?><option value="<?= (int)$slot['id_slot'] ?>"><?= h($slot['slot']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <?php if ($operational): ?><label class="hr_form_label"><span class="hr_form_label_text">Ukončit slot od</span><select name="ukoncit_slot"><option value="">Bez ukončení slotu</option><?php foreach ($positionData['historie'] as $slot): if ((int)$slot['platny']!==1 || !empty($slot['zruseno']) || (!empty($slot['platnost_do']) && $slot['platnost_do']<date('Y-m-d'))) continue; ?><option value="<?= (int)$slot['id_zarazeni'] ?>"><?= h($slot['slot']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label class="hr_form_label"><?php if ($operational): ?><span class="hr_form_label_text">Funkce osoby</span><?php endif; ?><select name="id_funkce" aria-label="<?= $operational?'Provozní funkce osoby':'Manažerská pozice' ?>"><option value=""><?= $operational?'Bez funkce':'Bez pozice' ?></option>
            <?php foreach ($functionData['funkce'] as $function): if ($function['zarazeni']!==$kind || ((int)$function['pouze_admin']===1 && !$isAdmin && (int)$function['id_funkce']!==$selected)) continue; ?>
            <option value="<?= (int)$function['id_funkce'] ?>"<?= (int)$function['id_funkce']===$selected?' selected':'' ?>><?= h($function['nazev']) ?></option><?php endforeach; ?>
        </select></label>
        <label class="hr_form_label"><span class="hr_form_label_text"><input type="checkbox" name="v_treninku" value="1"<?= $selected>0 && (int)($currentPersonFunction['v_treninku']??0)===1?' checked':'' ?>> V tréninku</span></label>
        <label class="hr_form_label"><span class="hr_form_label_text">Platí od</span><input name="platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label>
        </fieldset>
    </form>
    <?php endif; ?>
    <?php if ($operational): ?>
        <?php if ($positionData['historie']===[]): ?><p class="hr_employee_compact_note">Zaměstnanec nemá přidělen slot.</p><?php else: ?>
        <div class="hr_table_wrap"><table class="hr_table hr_assignment_history_table"><thead><tr><th class="hr_table_cell hr_table_head">Slot</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Stav</th></tr></thead><tbody>
        <?php foreach ($positionData['historie'] as $item): ?><tr><td class="hr_table_cell"><strong><?= h($item['slot']) ?></strong></td><td class="hr_table_cell"><?= h(hr_format_date((string)($item['platnost_od']??''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($item['platnost_do']??''))) ?></td><td class="hr_table_cell"><?= (int)$item['platny']!==1 || !empty($item['zruseno'])?'Zrušený':(!empty($item['platnost_do']) && $item['platnost_do']<date('Y-m-d')?'Ukončený':(!empty($item['platnost_od']) && $item['platnost_od']>date('Y-m-d')?'Plánovaný':'Platný')) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    <?php endif; ?>
    <?php if ($history===[]): ?><p class="hr_employee_compact_note"><?= $operational?'Funkce není evidována.':'Pozice není evidována.' ?></p><?php else: ?>
    <div class="hr_table_wrap"><table class="hr_table hr_assignment_history_table"><thead><tr><th class="hr_table_cell hr_table_head"><?= $operational?'Funkce':'Pozice' ?></th><th class="hr_table_cell hr_table_head">Trénink</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Stav</th></tr></thead><tbody>
        <?php foreach ($history as $item): ?><tr><td class="hr_table_cell"><strong><?= h($item['nazev']) ?></strong></td><td class="hr_table_cell"><?= (int)$item['v_treninku']===1?'Ano':'Ne' ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($item['platnost_od']??''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($item['platnost_do']??''))) ?></td><td class="hr_table_cell"><?= (int)$item['platny']!==1?'Ukončená':(!empty($item['platnost_od']) && $item['platnost_od']>date('Y-m-d')?'Plánovaná':(!empty($item['platnost_do']) && $item['platnost_do']<date('Y-m-d')?'Ukončená':'Platná')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php endforeach; ?>
