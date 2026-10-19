<?php
declare(strict_types=1);
/* Účel: Úvodní přehled ukazuje zveřejněné a definitivní rozpisy a zachovanou historii souhlasů i výhrad; nabízí společný přepínač vzhledu modulu. */
$smReadPage='prehled';
?>
<section class="pp smeny_content <?= cb_smeny_styl_trida($smStyl) ?> smeny_planning" data-module="smeny" data-page="prehled">
  <header class="pp_header smeny_overview_header"><h1>Přehled směn</h1>
    <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=prehled')) ?>" class="smeny_style_switch" data-smeny-style-form>
      <input type="hidden" name="action" value="smeny_styl_ulozit"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
      <fieldset><legend>Vzhled Směn</legend>
        <label><input type="radio" name="smeny_styl" value="1"<?= $smStyl===1?' checked':'' ?>> Klasický</label>
        <label><input type="radio" name="smeny_styl" value="2"<?= $smStyl===2?' checked':'' ?>> Nový</label>
      </fieldset><span role="status" data-smeny-style-status></span>
    </form>
  </header>
  <?php if(!cb_smeny_planovani_vidi()): ?>
    <?php $smPersonalNotices=cb_smeny_verejne_oznameni($smDb,cb_smeny_sablony_id_person($smDb)); ?>
    <p>Čeká na váš souhlas: <strong><?= count($smPersonalNotices) ?><?= count($smPersonalNotices)===100?'+':'' ?></strong> oznámení.</p>
    <?php if(cb_smeny_pozadavky_ma_pravo()): ?><p><a href="<?= h(cb_root_url('index.php?m=smeny&page=pozadavky')) ?>">Zadat požadavky na směny</a></p><?php endif; ?>
  <?php else:
    $smOverviewPlans=cb_smeny_prehled_rozpisy($smDb,$smReadBranches,$smReadWeek['start_day']);
    $smOverviewNotices=cb_smeny_prehled_souhlasy($smDb,$smReadBranches,$smReadWeek['start_day']);
    $smPendingCount=count(array_filter($smOverviewNotices,static fn(array $n):bool=>$n['potvrzeno']===null && ($n['nahrazeno']??null)===null));
  ?>
    <?php require __DIR__.'/tyden_filtr.php'; ?>
    <h2>Rozpisy poboček</h2>
    <div class="smeny_table_scroll"><table class="smeny_overview_table"><thead><tr><th>Pobočka</th><th>Zaměstnanci vidí</th><th>Pracovní plán</th></tr></thead><tbody>
    <?php foreach($smOverviewPlans as $smOverviewPlan): ?><tr>
      <th><?= h($smOverviewPlan['nazev']) ?></th><td><?= $smOverviewPlan['zverejnena_verze']===null?'Externí směny':($smOverviewPlan['definitivni_od']===null?'Zveřejněno · čeká na souhlasy':'Definitivně platný rozpis') ?></td>
      <td><?= $smOverviewPlan['pracovni_verze']===null?'Bez rozpracovaných změn':((int)$smOverviewPlan['pripraveno']===1?'Připraven ke zveřejnění':'Rozpracovaný') ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php if($smOverviewPlans===[]): ?><p>Nemáte vybranou dostupnou pobočku.</p><?php endif; ?>
    <h2>Souhlasy zaměstnanců</h2>
    <p>K vyjádření: <strong><?= $smPendingCount ?></strong> · Potvrzeno: <strong><?= count(array_filter($smOverviewNotices,static fn(array $n):bool=>$n['potvrzeno']!==null)) ?></strong> oznámení.</p>
    <?php if($smOverviewNotices===[]): ?><p>Pro tento týden nejsou zveřejněná žádná oznámení.</p><?php else: ?>
    <div class="smeny_table_scroll"><table class="smeny_overview_table"><thead><tr><th>Zaměstnanec</th><th>Pobočka</th><th>Oznámení</th><th>Souhlas</th></tr></thead><tbody>
    <?php foreach($smOverviewNotices as $smOverviewNotice): ?><tr>
      <th><?= h($smOverviewNotice['jmeno']) ?></th><td><?= h($smOverviewNotice['pobocka']) ?></td>
      <td><?= h(cb_smeny_oznameni_text($smOverviewNotice['typ'],json_decode($smOverviewNotice['obsah'],true,512,JSON_THROW_ON_ERROR))) ?><small>Zveřejnění č. <?= (int)$smOverviewNotice['verze'] ?></small></td>
      <td><?= $smOverviewNotice['potvrzeno']!==null?h(cb_format('dc',$smOverviewNotice['potvrzeno'])):(!empty($smOverviewNotice['vyhrady_dny'])?'Má výhrady':'Čeká') ?>
        <?php if(!empty($smOverviewNotice['vyhrady_dny'])): ?><small><?= h(implode(', ',array_map(static fn(string $day):string=>cb_format('d',$day),json_decode($smOverviewNotice['vyhrady_dny'],true,512,JSON_THROW_ON_ERROR)))) ?></small><?php endif; ?>
        <?php if(($smOverviewNotice['nahrazeno']??null)!==null): ?><small>Nahrazeno novější podobou směn</small><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  <?php endif; ?>
</section>
