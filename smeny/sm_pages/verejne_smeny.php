<?php
declare(strict_types=1);
/* Účel: Zobrazí aktuální vlastní směny nebo vedoucí tabulku naplánovaných směn bez historického výběru. */
$smAll=$smPage==='naplanovane_smeny';
$smPublicWeekIndex=max(0,min(count($smPublicWeeks)-1,(int)($_GET['week']??0)));
$smPublicWeek=$smPublicWeeks[$smPublicWeekIndex];
$smReadWeek=$smPublicWeek;$smReadWeeks=$smPublicWeeks;$smReadPage=$smPage;
$smOwnCancellations=$smAll?[]:cb_smeny_zruseni_nacist($smDb,$smPublicWeek['start_day'],$smPublicPerson,[],false);
$smOpenCancellations=[];
foreach($smOwnCancellations as $smCancellation) if(in_array($smCancellation['stav'],['odeslana','resi_se'],true) && $smCancellation['aktualni_smena']!==null) $smOpenCancellations[(int)$smCancellation['aktualni_smena']]=true;
$smPublicNow=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
$smPublicRows=cb_smeny_verejne_smeny($smDb,$smPublicWeek['start_day'],$smPublicPerson,$smPublicBranches,$smAll);
$smNotices=$smAll?[]:cb_smeny_verejne_oznameni($smDb,$smPublicPerson);
$smPublicFlash=cb_smeny_sablony_flash_nacist();
$smDetailId=$smAll?0:max(0,(int)($_GET['oznameni']??0));
$smDetail=cb_smeny_oznameni_detail($smDb,$smDetailId,$smPublicPerson);
?>
<section class="pp smeny_content smeny_planning" data-module="smeny" data-page="<?= h($smPage) ?>">
  <header class="pp_header"><h1><?= $smAll?'Naplánované směny':'Mé směny' ?></h1></header>
  <?php if($smPublicFlash): ?><p class="smeny_notice smeny_notice--<?= $smPublicFlash['type']==='success'?'success':'error' ?>"><?= h($smPublicFlash['text']) ?></p><?php endif; ?>
  <?php if($smAll && !cb_smeny_planovani_vidi()): ?><p class="smeny_notice">Nemáte právo zobrazit směny ostatních pracovníků.</p>
  <?php elseif(!$smAll && $smPublicPerson<=0): ?><p class="smeny_notice">Účet není propojený s osobou v HR.</p>
  <?php else: ?>
    <?php require __DIR__.'/tyden_filtr.php'; ?>
    <?php if($smAll): ?>
      <?php $smPublicRowsAll=$smPublicRows;$smTableWeekIndex=$smPublicWeekIndex;require __DIR__.'/verejne_tabulka.php'; ?>
    <?php else: ?>
    <div class="smeny_planning_days">
    <?php foreach($smPublicWeek['days'] as $day): ?><article><strong><?= h($day['name'].' '.$day['date_label']) ?></strong>
      <?php $found=false;foreach($smPublicRows as $row): if($row['datum']!==$day['date'])continue;$found=true; ?>
        <div class="smeny_planning_block"><span><?= h(substr($row['cas_od'],0,5).'–'.substr($row['cas_do'],0,5)) ?></span><span><?= h($row['pobocka'].' · '.$row['slot']) ?></span><strong><?= h($smAll?$row['pracovnik']:((int)$row['zdroj']===2?'Zveřejněno v IS':'Externí plán')) ?></strong></div>
        <?php if(!$smAll && (int)$row['zdroj']===2 && new DateTimeImmutable($row['zacatek'],new DateTimeZone('Europe/Prague'))>$smPublicNow): ?>
          <?php if(isset($smOpenCancellations[(int)$row['id_smeny_smena']])): ?><small>Žádost o zrušení čeká na vyřízení nebo zveřejnění.</small>
          <?php else: ?><details><summary>Požádat o zrušení směny</summary><form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=me_smeny')) ?>" class="smeny_cancel_form">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="week" value="<?= $smPublicWeekIndex ?>"><input type="hidden" name="id_smeny_smena" value="<?= (int)$row['id_smeny_smena'] ?>">
            <label>Důvod (nepovinný)<textarea name="poznamka" maxlength="500" rows="2"></textarea></label><button class="smeny_secondary_button" name="action" value="smeny_zruseni_zadat">Odeslat žádost</button>
          </form></details><?php endif; ?>
        <?php endif; ?>
      <?php endforeach;if(!$found): ?><small>Bez naplánovaných směn</small><?php endif; ?></article>
    <?php endforeach; ?></div>
      <?php require __DIR__.'/zruseni_vlastni.php'; ?>
      <h2>K potvrzení</h2>
      <?php if($smDetailId>0 && $smDetail===null): ?><p class="smeny_notice">Oznámení není dostupné pro váš účet.</p><?php endif; ?>
      <?php if($smNotices===[]): ?><p>Nemáte žádný týden ani změnu k potvrzení.</p><?php endif; ?>
      <?php foreach($smNotices as $notice): $content=json_decode($notice['obsah'],true,512,JSON_THROW_ON_ERROR); ?>
        <article class="smeny_notice">
          <strong><?= h($notice['pobocka']) ?></strong><p><?= h(cb_smeny_oznameni_text($notice['typ'],$content)) ?></p>
          <a class="smeny_primary_button" href="<?= h(cb_root_url('index.php?m=smeny&page=me_smeny&week='.$smPublicWeekIndex.'&oznameni='.(int)$notice['id_smeny_oznameni'])) ?>">Zobrazit a potvrdit</a>
        </article>
      <?php endforeach; ?>
      <?php if(count($smNotices)===100): ?><p>Další oznámení se zobrazí po potvrzení těchto zpráv.</p><?php endif; ?>
      <?php if($smDetail!==null)require __DIR__.'/oznameni_modal.php'; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
