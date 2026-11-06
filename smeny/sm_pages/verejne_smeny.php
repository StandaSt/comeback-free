<?php
declare(strict_types=1);
/* Účel: Denní přehledy naplánovaných i vlastních směn čte výhradně ze zveřejněných verzí IS; připravuje také vlastní oznámení bez rozšíření oprávnění. */
require_once __DIR__.'/../sm_lib/sm_verejne_prehled.php';
$smAll=$smPage==='naplanovane_smeny';
$smPublicWeekIndex=max(0,min(count($smPublicWeeks)-1,(int)($_GET['week']??0)));
$smPublicWeek=$smPublicWeeks[$smPublicWeekIndex];
$smReadWeek=$smPublicWeek;$smReadWeeks=$smPublicWeeks;$smReadPage=$smPage;
// Zdroj obou denních přehledů je pevné pravidlo stránky, nelze ho vypnout parametrem URL.
$smInternalOnly = true;
if ($smAll) $smPublicBranches=cb_smeny_verejne_rozsah($smDb);
$smOwnBranches=cb_smeny_verejne_osobni_pobocky($smDb,$smPublicPerson,$smPublicWeeks[0]['start_day'],$smPublicWeeks[count($smPublicWeeks)-1]['start_day'],$smInternalOnly);
$smPublicSlots=cb_smeny_sloty_ciselnik($smDb);
// Osobu přebíráme výhradně z přihlášení; filtr nedovoluje zadat cizí ID.
$smOwnRows=cb_smeny_verejne_smeny($smDb,$smPublicWeek['start_day'],$smPublicPerson,[],false,$smInternalOnly);
$smPublicRows=$smAll?cb_smeny_verejne_smeny($smDb,$smPublicWeek['start_day'],$smPublicPerson,$smPublicBranches,true,$smInternalOnly):$smOwnRows;
cb_smeny_souhlasy_doplnit($smDb,$smPublicRows);
if ($smAll) cb_smeny_souhlasy_doplnit($smDb,$smOwnRows);
else $smOwnRows=$smPublicRows;
$smNotices=$smAll?[]:cb_smeny_verejne_oznameni($smDb,$smPublicPerson);
$smPublicFlash=cb_smeny_sablony_flash_nacist();
$smDetailId=$smAll?0:max(0,(int)($_GET['oznameni']??0));
$smDetail=cb_smeny_oznameni_detail($smDb,$smDetailId,$smPublicPerson);
?>
<section class="pp smeny_content <?= cb_smeny_styl_trida($smStyl) ?> smeny_planning" data-module="smeny" data-page="<?= h($smPage) ?>">
  <header class="pp_header"><h1><?= $smAll?'Naplánované směny':'Mé směny' ?></h1></header>
  <?php if($smPublicFlash): ?><p class="smeny_notice smeny_notice--<?= $smPublicFlash['type']==='success'?'success':'error' ?>"><?= h($smPublicFlash['text']) ?></p><?php endif; ?>
  <?php if($smAll && !cb_smeny_planovani_vidi()): ?><p class="smeny_notice">Nemáte právo zobrazit směny ostatních pracovníků.</p>
  <?php elseif(!$smAll && $smPublicPerson<=0): ?><p class="smeny_notice">Účet není propojený s osobou v HR.</p>
  <?php else: ?>
    <?php require __DIR__.'/verejne_tabulka.php'; ?>
    <?php if(!$smAll): ?>
      <h2>K potvrzení</h2>
      <?php if($smDetailId>0 && $smDetail===null): ?><p class="smeny_notice">Oznámení není dostupné pro váš účet.</p><?php endif; ?>
      <?php if($smNotices===[]): ?><p>Nemáte žádný týden ani změnu k potvrzení.</p><?php endif; ?>
      <?php foreach($smNotices as $notice): $content=json_decode($notice['obsah'],true,512,JSON_THROW_ON_ERROR); ?>
        <article class="smeny_notice">
          <strong><?= h($notice['pobocka']) ?></strong><p><?= h(cb_smeny_oznameni_text($notice['typ'],$content)) ?></p>
          <?php if(!empty($notice['vyhrady_dny'])): ?><p>Máte výhrady k označeným směnám.</p><?php endif; ?>
          <a class="smeny_primary_button" href="<?= h(cb_root_url('index.php?m=smeny&page=me_smeny&week='.$smPublicWeekIndex.'&oznameni='.(int)$notice['id_smeny_oznameni'])) ?>">Zobrazit a potvrdit</a>
        </article>
      <?php endforeach; ?>
      <?php if(count($smNotices)===100): ?><p>Další oznámení se zobrazí po potvrzení těchto zpráv.</p><?php endif; ?>
      <?php if($smDetail!==null)require __DIR__.'/oznameni_modal.php'; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
