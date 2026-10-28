<?php
declare(strict_types=1);
/* Účel: Vykreslí kompaktní filtry, stránkovaný seznam a samostatný detail historie bez klientského skládání oprávnění. */
$hcModuleNames = array_column($hcModules, 'modul', 'id_modul');
$hcModeLabels = ['uzivatel'=>'Podle uživatele','agenda'=>'Podle agendy','zaznam'=>'Podle záznamu'];
?>
<link rel="stylesheet" href="<?= h(cb_root_url('administrace/style/admin_historie.css').'?v='.filemtime(__DIR__.'/../style/admin_historie.css')) ?>">
<section class="hc_prehled" aria-label="Historie činnosti">
    <nav class="hc_tabs" aria-label="Pohled historie">
        <?php foreach ($hcModeLabels as $key=>$label): ?>
        <a href="<?= h(cb_historie_url($hcFilters,['hc_pohled'=>$key,'hc_strana'=>1])) ?>"<?= $hcFilters['pohled']===$key?' aria-current="page"':'' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="hc_filtry" data-pohled="<?= h($hcFilters['pohled']) ?>" method="get" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="m" value="administrace"><input type="hidden" name="page" value="historie_cinnosti"><input type="hidden" name="hc_pohled" value="<?= h($hcFilters['pohled']) ?>">
        <label>Od<input type="date" name="hc_od" value="<?= h($hcFilters['od']) ?>" required></label>
        <label>Do<input type="date" name="hc_do" value="<?= h($hcFilters['do']) ?>" required></label>
        <label class="hc_siroky hc_autor">Kdo provedl<select name="hc_autor"><option value="0">Všichni uživatelé</option><?php foreach ($hcNames['autori'] as $id=>$name): ?><option value="<?= (int)$id ?>"<?= $hcFilters['autor']===$id?' selected':'' ?>><?= h($name).' · #'.(int)$id ?></option><?php endforeach; ?></select></label>
        <label class="hc_modul">Agenda<select name="hc_modul"><option value="0">Všechny agendy</option><?php foreach ($hcModules as $module): ?><option value="<?= (int)$module['id_modul'] ?>"<?= $hcFilters['modul']===(int)$module['id_modul']?' selected':'' ?>><?= h($module['modul']) ?></option><?php endforeach; ?></select></label>
        <label>Zobrazit<select name="hc_druh"><?php foreach (['vecne'=>'Věcné události','vse'=>'Vše včetně ovládání','chyby'=>'Neúspěšné události'] as $key=>$label): ?><option value="<?= h($key) ?>"<?= $hcFilters['druh']===$key?' selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
        <label>Zdroj<select name="hc_zdroj"><option value="">Všechny dostupné zdroje</option><?php foreach ($hcSources as $key=>$source): ?><option value="<?= h($key) ?>"<?= $hcFilters['zdroj']===$key?' selected':'' ?>><?= h($source['label']) ?></option><?php endforeach; ?></select></label>
        <label class="hc_siroky hc_zaznam">Koho se týká<select name="hc_osoba"><option value="0">Všichni zaměstnanci</option><?php foreach ($hcNames['osoby'] as $id=>$name): ?><option value="<?= (int)$id ?>"<?= $hcFilters['osoba']===$id?' selected':'' ?>><?= h($name).' · #'.(int)$id ?></option><?php endforeach; ?></select></label>
        <label class="hc_zaznam">Pobočka reportu<select name="hc_pobocka"><option value="0">Všechny dostupné</option><?php foreach ($hcScope['branches'] as $id=>$branch): ?><option value="<?= (int)$id ?>"<?= $hcFilters['pobocka']===$id?' selected':'' ?>><?= h($branch['name']) ?></option><?php endforeach; ?></select></label>
        <label class="hc_zaznam">Den reportu<input type="date" name="hc_datum" value="<?= h($hcFilters['datum']) ?>"></label>
        <label>Řádků<select name="hc_pocet"><?php foreach ([25,50,100] as $count): ?><option<?= $hcFilters['pocet']===$count?' selected':'' ?>><?= $count ?></option><?php endforeach; ?></select></label>
        <div class="hc_akce"><button type="submit">Zobrazit</button><a href="<?= h(cb_root_url('index.php?m=administrace&page=historie_cinnosti')) ?>">Zrušit filtry</a></div>
    </form>
    <p class="hc_poznamka">Čas zápisu · existující záznamy bez doplňování chybějící historie. Filtry se kombinují; ne každý zdroj obsahuje vazbu na zaměstnance nebo report.</p>
    <?php if ($hcDetail !== null) require __DIR__.'/admin_historie_detail.php'; ?>
    <div class="hc_tabulka_wrap">
        <table class="hc_tabulka">
            <thead><tr><th>Čas zápisu</th><th>Kdo provedl</th><th>Agenda</th><th>Událost</th><th>Týká se</th><th>Výsledek</th><th></th></tr></thead>
            <tbody>
            <?php if ($hcRows === []): ?><tr><td colspan="7">Pro zvolené filtry nejsou dostupné žádné události.</td></tr><?php endif; ?>
            <?php foreach ($hcRows as $row): $description=cb_historie_popis($row); ?>
                <tr<?= (int)$row['vysledek']===0?' class="hc_neuspech"':'' ?>>
                    <td class="hc_cas"><?= h(cb_format('dcs',$row['cas'])) ?></td>
                    <td title="<?= (int)$row['autor']>0?'ID uživatele '.(int)$row['autor']:'' ?>"><?= h((int)$row['autor']>0?($hcNames['autori'][(int)$row['autor']]??'Uživatel #'.$row['autor']):'Autor nedoložen') ?></td>
                    <td><?= h($hcModuleNames[(int)$row['modul']]??'Přihlášení / systém') ?></td>
                    <td><strong><?= h($description['nazev']) ?></strong><?php if ($description['kontext']!==''): ?><small><?= h($description['kontext']) ?></small><?php endif; ?></td>
                    <td><?php if ((int)$row['osoba']>0): ?><?= h($hcNames['jmena'][(int)$row['osoba']]??'Osoba #'.$row['osoba']) ?><?php endif; ?><?php if ((int)$row['pobocka']>0): ?><small><?= h($hcScope['branches'][(int)$row['pobocka']]['name']??'Pobočka #'.$row['pobocka']) ?><?= $row['datum']?' · '.h(cb_format('d',$row['datum'])):'' ?></small><?php endif; ?></td>
                    <td><span class="hc_stav"><?= h($description['stav']) ?></span></td>
                    <td><a href="<?= h(cb_historie_url($hcFilters,['hc_detail'=>$row['zdroj'].':'.$row['id']])) ?>#hc-detail">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <nav class="hc_strankovani" aria-label="Stránkování historie">
        <?php if ($hcFilters['strana']>1): ?><a href="<?= h(cb_historie_url($hcFilters,['hc_strana'=>$hcFilters['strana']-1])) ?>">← Novější</a><?php endif; ?>
        <span>Strana <?= $hcFilters['strana'] ?> · <?= count($hcRows) ?> událostí</span>
        <?php if ($hcMore && $hcFilters['strana']<100): ?><a href="<?= h(cb_historie_url($hcFilters,['hc_strana'=>$hcFilters['strana']+1])) ?>">Starší →</a><?php elseif ($hcMore): ?><span>Pro starší události zúžte období.</span><?php endif; ?>
    </nav>
</section>
