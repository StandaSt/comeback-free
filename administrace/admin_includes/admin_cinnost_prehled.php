<?php
declare(strict_types=1);
/* Účel: Zobrazuje konkrétní činnosti z nové historie Helpdesku; nezávislé detaily dovolují porovnání více událostí. */
?>
<link rel="stylesheet" href="<?= h(cb_root_url('administrace/admin_style/admin_historie.css').'?v='.filemtime(__DIR__.'/../admin_style/admin_historie.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_root_url('administrace/admin_style/admin_cinnost.css').'?v='.filemtime(__DIR__.'/../admin_style/admin_cinnost.css')) ?>">
<section class="hc_prehled" data-nc-prehled="1" aria-label="Historie činnosti">
<form class="hc_form" method="get" action="<?= h(cb_root_url('index.php')) ?>">
    <input type="hidden" name="m" value="administrace"><input type="hidden" name="page" value="historie_cinnosti">
    <div class="hc_filtry">
        <label>Od<input type="date" name="nc_od" value="<?= h($ncFilters['od']) ?>"></label>
        <label>Do<input type="date" name="nc_do" value="<?= h($ncFilters['do']) ?>"></label>
        <label class="hc_siroky">Kdo provedl<select name="nc_autor"><option value="0">Všichni uživatelé</option><?php foreach ($ncData['authors'] as $author): ?><option value="<?= (int)$author['id_user'] ?>"<?= $ncFilters['autor']===(int)$author['id_user']?' selected':'' ?>><?= h($author['nazev'] ?: 'Uživatel č. '.$author['id_user']) ?></option><?php endforeach; ?></select></label>
        <label>Agenda<select aria-label="Agenda"><option>Helpdesk</option></select></label>
        <label>Událost<select name="nc_akce"><option value="0">Všechny akce</option><?php foreach ($ncData['actions'] as $action): ?><option value="<?= (int)$action['id_akce'] ?>"<?= $ncFilters['akce']===(int)$action['id_akce']?' selected':'' ?>><?= h($action['nazev']) ?></option><?php endforeach; ?></select></label>
        <label>Výsledek<select name="nc_stav"><option value="0">Všechny výsledky</option><?php foreach ($ncData['states'] as $state): ?><option value="<?= (int)$state['id_stav'] ?>"<?= $ncFilters['stav']===(int)$state['id_stav']?' selected':'' ?>><?= h($state['nazev']) ?></option><?php endforeach; ?></select></label>
        <label>Číslo tiketu<input type="number" min="1" max="2147483647" name="nc_tiket" value="<?= $ncFilters['tiket'] ?: '' ?>"></label>
        <label>Řazení<select name="nc_smer"><option value="desc"<?= $ncFilters['smer']==='desc'?' selected':'' ?>>Od nejnovějších</option><option value="asc"<?= $ncFilters['smer']==='asc'?' selected':'' ?>>Od nejstarších</option></select></label>
        <label>Počet řádků<select name="nc_pocet"><?php foreach ([25,50,100] as $count): ?><option<?= $count===$ncFilters['pocet']?' selected':'' ?>><?= $count ?></option><?php endforeach; ?></select></label>
        <div class="hc_akce"><button type="submit">Zobrazit</button><a href="<?= h(cb_root_url('index.php?m=administrace&page=historie_cinnosti')) ?>">Zrušit filtry</a></div>
    </div>
</form>
<p class="hc_poznamka">Nová historie činnosti · zatím je zapojený Helpdesk. Bez nastavení filtrů se zobrazují všechny jeho dostupné akce.</p>
<div class="hc_tabulka_wrap"><table class="hc_tabulka"><thead><tr><th>Kdy</th><th>Kdo provedl</th><th>Co udělal</th><th>Výsledek</th><th></th></tr></thead><tbody>
<?php if ($ncData['rows']===[]): ?><tr><td colspan="5">Dosud nejsou zaznamenané žádné činnosti odpovídající zvoleným filtrům.</td></tr><?php endif; ?>
<?php foreach ($ncData['rows'] as $row): $desc=cb_admin_cinnost_popis($row); $detailId='nc-detail-'.$row['id_cinnost']; $author=$row['autor_nazev'] ?: 'Uživatel č. '.$row['id_user']; ?>
<tr<?= (int)$row['id_stav']===5?' class="hc_neuspech"':'' ?>>
    <td class="hc_cas"><?= h($desc['cas']) ?></td><td><?= h($author) ?></td>
    <td><?= h($desc['text']) ?><?php if ($desc['cil']!=='' && !str_contains($desc['text'],$desc['cil'])): ?><small><?= h($desc['cil']) ?></small><?php endif; ?></td>
    <td><span class="hc_stav"><?= h($row['stav_nazev']) ?></span></td>
    <td><button type="button" data-nc-toggle="<?= h($detailId) ?>" aria-controls="<?= h($detailId) ?>" aria-expanded="false">Detail</button></td>
</tr>
<tr id="<?= h($detailId) ?>" class="hc_detail_radek" hidden><td colspan="5"><section class="hc_detail" aria-label="Detail události">
    <header><strong><?= h($desc['text']) ?></strong><button type="button" data-nc-toggle="<?= h($detailId) ?>" aria-controls="<?= h($detailId) ?>" aria-expanded="false">Zavřít detail</button></header>
    <dl><?php foreach (['Kdy'=>$desc['cas'],'Kdo'=>$author,...$desc['fields'],'Výsledek'=>$row['stav_nazev']] as $label=>$value): ?><div><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd></div><?php endforeach; ?></dl>
</section></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="cb_table_footer list-bottom" aria-label="Stránkování historie">
    <div>Zobrazuji <?= $ncData['total']?($ncFilters['strana']-1)*$ncFilters['pocet']+1:0 ?>–<?= $ncData['total']?($ncFilters['strana']-1)*$ncFilters['pocet']+count($ncData['rows']):0 ?> z <?= $ncData['total'] ?> událostí</div>
    <div class="pagination-icon"><?php if ($ncFilters['strana']>1): ?><a href="<?= h(cb_admin_cinnost_url($ncFilters,['strana'=>$ncFilters['strana']-1])) ?>">Předchozí</a><?php endif; ?>
        <span><?= $ncFilters['strana'] ?> / <?= $ncData['pages'] ?></span>
        <?php if ($ncFilters['strana']<$ncData['pages']): ?><a href="<?= h(cb_admin_cinnost_url($ncFilters,['strana'=>$ncFilters['strana']+1])) ?>">Další</a><?php endif; ?></div>
</div>
</section>
