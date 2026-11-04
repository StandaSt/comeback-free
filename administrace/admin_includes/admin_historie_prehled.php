<?php
declare(strict_types=1);
/* Účel: Vykreslí filtry, stránkovaný seznam i detail historie; společný filtry.js mění obsah přehledu bez nové navigace do Administrace. */
$hcModuleNames = array_column($hcModules, 'modul', 'id_modul');
$hcModeLabels = ['uzivatel'=>'Podle uživatele','agenda'=>'Podle agendy','zaznam'=>'Podle záznamu'];
// Přímý odkaz na událost mimo aktuální stránku ponechá detail nad tabulkou; jinak patří pod její řádek.
$hcDetailInRows = $hcDetail !== null && array_filter($hcRows, static fn(array $row): bool =>
    $row['zdroj'] === $hcDetail['event']['zdroj'] && (string)$row['id'] === (string)$hcDetail['event']['id']) !== [];
?>
<link rel="stylesheet" href="<?= h(cb_root_url('administrace/admin_style/admin_historie.css').'?v='.filemtime(__DIR__.'/../admin_style/admin_historie.css')) ?>">
<section class="hc_prehled" aria-label="Historie činnosti">
    <form class="hc_form cb_table_view" data-cb-filter-default-per="50" data-pohled="<?= h($hcFilters['pohled']) ?>" method="get" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="m" value="administrace"><input type="hidden" name="page" value="historie_cinnosti"><input type="hidden" name="hc_pohled" value="<?= h($hcFilters['pohled']) ?>">
        <input type="hidden" name="hc_p" value="<?= $hcFilters['strana'] ?>">
        <div class="table-wrap">
    <nav class="hc_tabs" aria-label="Pohled historie">
        <?php foreach ($hcModeLabels as $key=>$label): ?>
        <a href="<?= h(cb_historie_url($hcFilters,['hc_pohled'=>$key,'hc_strana'=>1])) ?>"<?= $hcFilters['pohled']===$key?' aria-current="page"':'' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($hcDetail !== null && !$hcDetailInRows) require __DIR__.'/admin_historie_detail.php'; ?>

        <div class="hc_filtry" data-pohled="<?= h($hcFilters['pohled']) ?>">
        <label>Od<input type="date" name="hc_od" value="<?= h($hcFilters['od']) ?>" required></label>
        <label>Do<input type="date" name="hc_do" value="<?= h($hcFilters['do']) ?>" required></label>
        <label class="hc_siroky hc_autor">Kdo provedl<select name="hc_autor"><option value="0">Všichni uživatelé</option><?php foreach ($hcNames['autori'] as $id=>$name): ?><option value="<?= (int)$id ?>"<?= $hcFilters['autor']===$id?' selected':'' ?>><?= h($name).' · #'.(int)$id ?></option><?php endforeach; ?></select></label>
        <label class="hc_modul">Agenda<select name="hc_modul"><option value="0">Všechny agendy</option><?php foreach ($hcModules as $module): ?><option value="<?= (int)$module['id_modul'] ?>"<?= $hcFilters['modul']===(int)$module['id_modul']?' selected':'' ?>><?= h($module['modul']) ?></option><?php endforeach; ?></select></label>
        <label>Zobrazit<select name="hc_druh"><?php foreach (['vecne'=>'Věcné události','vse'=>'Všechny uživatelské akce','chyby'=>'Neúspěšné události'] as $key=>$label): ?><option value="<?= h($key) ?>"<?= $hcFilters['druh']===$key?' selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
        <label>Zdroj<select name="hc_zdroj"><option value="">Všechny dostupné zdroje</option><?php foreach ($hcSources as $key=>$source): ?><option value="<?= h($key) ?>"<?= $hcFilters['zdroj']===$key?' selected':'' ?>><?= h($source['label']) ?></option><?php endforeach; ?></select></label>
        <label class="hc_siroky hc_zaznam">Koho se týká<select name="hc_osoba"><option value="0">Všichni zaměstnanci</option><?php foreach ($hcNames['osoby'] as $id=>$name): ?><option value="<?= (int)$id ?>"<?= $hcFilters['osoba']===$id?' selected':'' ?>><?= h($name).' · #'.(int)$id ?></option><?php endforeach; ?></select></label>
        <label class="hc_zaznam">Pobočka reportu<select name="hc_pobocka"><option value="0">Všechny dostupné</option><?php foreach ($hcScope['branches'] as $id=>$branch): ?><option value="<?= (int)$id ?>"<?= $hcFilters['pobocka']===$id?' selected':'' ?>><?= h($branch['name']) ?></option><?php endforeach; ?></select></label>
        <label class="hc_zaznam">Den reportu<input type="date" name="hc_datum" value="<?= h($hcFilters['datum']) ?>"></label>
        <div class="hc_akce"><button type="submit">Zobrazit</button><a class="filter-reset-btn" href="<?= h(cb_root_url('index.php?m=administrace&page=historie_cinnosti')) ?>">Zrušit filtry</a></div>
        </div>
    <p class="hc_poznamka">Čas zápisu · existující záznamy bez doplňování chybějící historie. Filtry se kombinují; ne každý zdroj obsahuje vazbu na zaměstnance nebo report.</p>
    <div class="hc_tabulka_wrap">
        <table class="hc_tabulka cb_table">
            <thead>
            <tr class="filter-row"><?php foreach (['cas'=>'Čas zápisu','autor'=>'Kdo provedl','agenda'=>'Agenda','udalost'=>'Událost','cil'=>'Týká se','vysledek'=>'Výsledek'] as $key=>$label): ?><th><input type="text" class="filter-input" name="hc_f[<?= h($key) ?>]" value="<?= h($hcFilters['sloupce'][$key]) ?>" aria-label="Filtrovat <?= h($label) ?>"></th><?php endforeach; ?><th></th></tr>
            <tr><th>Čas zápisu</th><th>Kdo provedl</th><th>Agenda</th><th>Událost</th><th>Týká se</th><th>Výsledek</th><th></th></tr></thead>
            <tbody>
            <?php if ($hcRows === []): ?><tr><td colspan="7">Pro zvolené filtry nejsou dostupné žádné události.</td></tr><?php endif; ?>
            <?php foreach ($hcRows as $row): $description=cb_historie_popis($row); ?>
                <tr<?= (int)$row['vysledek']===0?' class="hc_neuspech"':'' ?>>
                    <td class="hc_cas"><?= h(cb_format('dcs',$row['cas'])) ?></td>
                    <td title="<?= (int)$row['autor']>0?'ID uživatele '.(int)$row['autor']:'' ?>"><?= h((int)$row['autor']>0?($hcNames['autori'][(int)$row['autor']]??'Uživatel #'.$row['autor']):'Autor nedoložen') ?></td>
                    <td><?= h($hcModuleNames[(int)$row['modul']]??'Přihlášení / systém') ?></td>
                    <td><strong><?= h($description['nazev']) ?></strong><?php if ($description['kontext']!==''): ?><small><?= h($description['kontext']) ?></small><?php endif; ?></td>
                    <td><?php if ($row['zdroj']==='co_link'): $coContext=json_decode((string)$row['kontext'],true); ?><?= h($coContext['cil']??'') ?><?php endif; ?><?php if ((int)$row['osoba']>0): ?><?= h($hcNames['jmena'][(int)$row['osoba']]??'Osoba #'.$row['osoba']) ?><?php endif; ?><?php if ((int)$row['pobocka']>0): ?><small><?= h($hcScope['branches'][(int)$row['pobocka']]['name']??'Pobočka #'.$row['pobocka']) ?><?= $row['datum']?' · '.h(cb_format('d',$row['datum'])):'' ?></small><?php endif; ?></td>
                    <td><span class="hc_stav"><?= h($description['stav']) ?></span></td>
                    <?php // Detail zpracuje stejná AJAX výměna jako filtry; nesmí propadnout do navigace modulů. ?>
                    <td><a href="<?= h(cb_historie_url($hcFilters,['hc_detail'=>$row['zdroj'].':'.$row['id']])) ?>#hc-detail">Detail</a></td>
                </tr>
                <?php if ($hcDetailInRows && $row['zdroj'] === $hcDetail['event']['zdroj'] && (string)$row['id'] === (string)$hcDetail['event']['id']): ?>
                <tr class="hc_detail_radek"><td colspan="7"><?php require __DIR__.'/admin_historie_detail.php'; ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
    <div class="cb_table_footer list-bottom" aria-label="Stránkování historie">
        <div class="cb_table_footer_summary">Zobrazuji řádky <?= $hcTotal ? ($hcFilters['strana']-1)*$hcFilters['pocet']+1 : 0 ?>–<?= $hcTotal ? ($hcFilters['strana']-1)*$hcFilters['pocet']+count($hcRows) : 0 ?> z celkem <strong><?= $hcTotal ?></strong></div>
        <div class="pagination-icon">
            <?php foreach (['«'=>1,'‹'=>max(1,$hcFilters['strana']-1),'›'=>min($hcPages,$hcFilters['strana']+1),'»'=>$hcPages] as $label=>$page): $disabled=in_array($label,['«','‹'],true)?$hcFilters['strana']<=1:$hcFilters['strana']>=$hcPages; ?>
                <?php if ($label==='›'): ?><span class="page-current"><?= $hcFilters['strana'] ?> / <?= $hcPages ?></span><?php endif; ?>
                <a class="icon-btn<?= $disabled?' disabled':'' ?>" aria-label="<?= h(['«'=>'První stránka','‹'=>'Předchozí stránka','›'=>'Další stránka','»'=>'Poslední stránka'][$label]) ?>"<?= $disabled?' aria-disabled="true" tabindex="-1"':'' ?> href="<?= $disabled?'#':h(cb_historie_url($hcFilters,['hc_p'=>$page])) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <label class="cb_table_footer_size">Zobrazovat <select name="hc_per" class="filter-input"><?php foreach ([25,50,100] as $count): ?><option value="<?= $count ?>"<?= $hcFilters['pocet']===$count?' selected':'' ?>><?= $count ?> řádků</option><?php endforeach; ?></select></label>
    </div>
    </form>
</section>
