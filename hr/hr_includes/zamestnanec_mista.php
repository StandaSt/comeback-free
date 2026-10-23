<?php
declare(strict_types=1);
/* Ucel: Vykresli spolecny vyber oblasti i konkretnich pobocek a nezavisly vyber vice MAIN v karte osoby. */
$personId=(int)$employee['id_person'];
$placeRegions=[]; $placeBranches=[];
$stmt=$db->prepare('SELECT i.id_oblast_prace,i.id_pob FROM hr_misto_vykonu_prace h
    JOIN hr_misto_vykonu_prace_polozka i USING(id_hr_misto_vykonu_prace)
    WHERE h.id_person=? AND '.cb_hr_ucinne_sql('h'));
$stmt->bind_param('i',$personId); $stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if ($row['id_oblast_prace']!==null) $placeRegions[]=(int)$row['id_oblast_prace'];
    if ($row['id_pob']!==null) $placeBranches[]=(int)$row['id_pob'];
}
$stmt->close();
$regions=$db->query('SELECT id_oblast_prace,nazev FROM cis_oblast_prace WHERE aktivni=1 ORDER BY poradi,nazev')->fetch_all(MYSQLI_ASSOC);
$branchRegions=[];
$result=$db->query('SELECT id_pob,id_oblast_prace FROM pobocka_oblast_prace a WHERE '.cb_hr_ucinne_sql('a'));
foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) $branchRegions[(int)$row['id_pob']][]=(int)$row['id_oblast_prace'];
$result->free();
$isManager=($currentPersonFunction['zarazeni']??'')==='manazerske';
$canEditPlaces=$isEdit && cb_pravo_ma(307);
?>
<section class="hr_panel" data-hr-employee-block="branches">
    <div class="hr_panel_header"><h2 class="hr_panel_title">Místo výkonu práce</h2>
        <span><?= h(cb_hr_mista_popis($db,$personId)) ?></span>
        <?php if ($canEditPlaces): ?><button class="hr_primary_button" type="submit" form="hr-branches-form" data-hr-employee-save>Uložit změny</button><?php endif; ?>
    </div>
    <form id="hr-branches-form" data-hr-employee-form="branches" data-hr-places class="hr_form hr_assignment_form"<?= $canEditPlaces?' method="post" action="'.h(hr_pracovni_pomer_url($personId)).'"':'' ?>>
        <?php if ($canEditPlaces): ?><input type="hidden" name="cb_action" value="hr_zamestnanec_pobocky_zmenit"><input type="hidden" name="id_person" value="<?= $personId ?>"><?php endif; ?>
        <fieldset class="hr_employee_mode_fields"<?= $canEditPlaces?'':' disabled' ?>>
            <fieldset class="hr_place_choices" data-hr-place-options aria-label="Místo výkonu práce">
                <?php foreach ($regions as $region): ?><label><input type="checkbox" name="id_oblast_prace[]" value="<?= (int)$region['id_oblast_prace'] ?>"<?= in_array((int)$region['id_oblast_prace'],$placeRegions,true)?' checked':'' ?>> <?= h($region['nazev']) ?></label><?php endforeach; ?>
                <?php foreach ($workplaceData['pobocky'] as $branch): ?><label><input type="checkbox" name="id_pob[]" value="<?= (int)$branch['id_pob'] ?>"<?= in_array((int)$branch['id_pob'],$placeBranches,true)?' checked':'' ?>> <?= h($branch['nazev']) ?></label><?php endforeach; ?>
            </fieldset>
            <fieldset class="hr_place_choices" data-hr-main-choices<?= $isManager?' disabled':'' ?>><legend>MAIN pobočky</legend>
                <?php if ($isManager): ?><span>Manažerská pozice nemá MAIN pobočky.</span><?php else: ?>
                <?php foreach ($workplaceData['pobocky'] as $branch): ?><label data-hr-main-choice><input type="checkbox" name="id_main[]" value="<?= (int)$branch['id_pob'] ?>" data-hr-regions="<?= h(implode(',',$branchRegions[(int)$branch['id_pob']]??[])) ?>"<?= isset($mainWorkplaces[(int)$branch['id_pob']])?' checked':'' ?>> <?= h($branch['nazev']) ?></label><?php endforeach; ?>
                <?php endif; ?>
            </fieldset>
            <div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Místo platí od</span><input name="platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">MAIN platí od</span><input name="main_platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label></div>
        </fieldset>
    </form>
    <details><summary>Historie míst výkonu práce a MAIN</summary>
        <?php
        $stmt=$db->prepare('SELECT h.*,GROUP_CONCAT(COALESCE(o.nazev,b.nazev) ORDER BY COALESCE(o.nazev,b.nazev) SEPARATOR ", ") AS nazev
            FROM hr_misto_vykonu_prace h LEFT JOIN hr_misto_vykonu_prace_polozka i USING(id_hr_misto_vykonu_prace)
            LEFT JOIN cis_oblast_prace o ON o.id_oblast_prace=i.id_oblast_prace LEFT JOIN pobocka b ON b.id_pob=i.id_pob
            WHERE h.id_person=? GROUP BY h.id_hr_misto_vykonu_prace ORDER BY h.platnost_od DESC,h.id_hr_misto_vykonu_prace DESC');
        $stmt->bind_param('i',$personId); $stmt->execute(); $placeHistory=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        // Stare povolene pobocky zustavaji pouze archivem a uz neurcuji zadne opravneni.
        foreach (['Místo výkonu práce'=>$placeHistory,'MAIN pobočky'=>$workplaceData['main_historie'],'Dřívější povolené pobočky'=>$workplaceData['historie']] as $title=>$history): ?>
        <h3><?= h($title) ?></h3><div class="hr_table_wrap"><table class="hr_table hr_assignment_history_table"><thead><tr><th class="hr_table_cell hr_table_head">Místo</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Stav</th></tr></thead><tbody>
            <?php foreach ($history as $item): ?><tr><td class="hr_table_cell"><?= h((string)$item['nazev']) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($item['platnost_od']??''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($item['platnost_do']??''))) ?></td><td class="hr_table_cell"><?= (int)$item['platny']!==1 || !empty($item['zruseno'])?'Zrušený':(!empty($item['platnost_do']) && $item['platnost_do']<date('Y-m-d')?'Ukončený':(!empty($item['platnost_od']) && $item['platnost_od']>date('Y-m-d')?'Plánovaný':'Platný')) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endforeach; ?>
    </details>
</section>
