<?php
declare(strict_types=1);
/* Ucel: Vykresli zalozeni aktivni oblasti, nemenne nazvy s okamzitym prepnutim stavu a prirazeni pobocek. */
$regions=$db->query('SELECT * FROM cis_oblast_prace ORDER BY poradi,nazev')->fetch_all(MYSQLI_ASSOC);
$firmIds=implode(',',array_map('intval',cb_firemni_pristup_firmy($db,hr_current_user_id())))?:'0';
$branches=$db->query("SELECT b.id_pob,b.nazev,a.id_oblast_prace,a.platnost_od FROM pobocka b LEFT JOIN pobocka_oblast_prace a ON a.id_pob=b.id_pob AND ".cb_hr_ucinne_sql('a')." WHERE b.aktivni=1 AND b.id_firma IN ($firmIds) ORDER BY b.nazev")->fetch_all(MYSQLI_ASSOC);
?>
<section class="hr_panel" id="hr-settings-oblasti"><div class="hr_panel_header"><h2 class="hr_panel_title">Oblasti místa výkonu práce</h2></div>
    <form class="hr_form hr_region_form" method="post" action="<?= h($settingsUrl) ?>"><input type="hidden" name="cb_action" value="hr_oblast_ulozit"><input type="hidden" name="id" value="0"><input type="hidden" name="navrat_person" value="<?= $returnPerson ?>">
        <label class="hr_form_label"><span>Nová oblast</span><input name="nazev" maxlength="100" required></label><button class="hr_primary_button" type="submit">Přidat</button>
    </form>
    <?php foreach ($regions as $region): ?>
    <form class="hr_form hr_region_state" data-hr-region-state data-region-id="<?= (int)$region['id_oblast_prace'] ?>" data-region-name="<?= h($region['nazev']) ?>" method="post" action="<?= h($settingsUrl) ?>"><input type="hidden" name="cb_action" value="hr_oblast_ulozit"><input type="hidden" name="id" value="<?= (int)$region['id_oblast_prace'] ?>"><input type="hidden" name="navrat_person" value="<?= $returnPerson ?>">
        <span><?= h($region['nazev']) ?></span><label><input type="checkbox" name="aktivni" value="1" aria-label="<?= h('Aktivní: '.$region['nazev']) ?>"<?= (int)$region['aktivni']===1?' checked':'' ?>> Aktivní</label>
        <span class="hr_region_status" data-hr-region-status role="status" hidden></span>
    </form><?php endforeach; ?>
</section>
<section class="hr_panel" id="hr-settings-oblasti-pobocky"><div class="hr_panel_header"><h2 class="hr_panel_title">Oblasti poboček</h2></div>
    <?php foreach ($branches as $branch): ?><form class="hr_form hr_branch_region_form" method="post" action="<?= h($settingsUrl) ?>"><input type="hidden" name="cb_action" value="hr_pobocka_oblast_ulozit"><input type="hidden" name="id_pob" value="<?= (int)$branch['id_pob'] ?>"><input type="hidden" name="navrat_person" value="<?= $returnPerson ?>">
        <label class="hr_branch_region_choice"><span><?= h($branch['nazev']) ?></span><select name="id_oblast_prace" required><option value="">Vyberte oblast</option><?php foreach ($regions as $region): if ((int)$region['aktivni']!==1) continue; ?><option value="<?= (int)$region['id_oblast_prace'] ?>"<?= (int)$region['id_oblast_prace']===(int)$branch['id_oblast_prace']?' selected':'' ?>><?= h($region['nazev']) ?></option><?php endforeach; ?></select></label>
        <label class="hr_branch_region_date"><span>Platí od</span><input name="platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label><button class="hr_primary_button" type="submit">Uložit</button>
    </form><?php endforeach; ?>
</section>
