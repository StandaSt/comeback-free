<?php
declare(strict_types=1);
/* Účel: V existujícím modálu zobrazí neměnnou verzi týdne se souhlasem a výhradami ke dnům, nebo detail jednotlivé změny s potvrzením převzetí. */
$smNoticeContent = json_decode($smDetail['obsah'], true, 512, JSON_THROW_ON_ERROR);
$smNoticeWeekly=$smDetail['typ']==='tyden' && ($smNoticeContent['smeny']??[])!==[];
$smNoticeObjections=json_decode($smDetail['vyhrady_dny']??'[]',true,512,JSON_THROW_ON_ERROR);
$smNoticeCurrent=($smDetail['nahrazeno']??null)===null;
$smNoticeCanReply=$smNoticeCurrent && $smDetail['potvrzeno']===null && cb_smeny_souhlasy_schema($smDb);
?>
<dialog class="smeny_oznameni_modal" data-smeny-oznameni-modal aria-labelledby="smeny-oznameni-title">
  <header><h2 id="smeny-oznameni-title"><?= $smDetail['typ']==='tyden'?'Zveřejněné směny':'Změna směny' ?></h2><button type="button" data-smeny-oznameni-close aria-label="Zavřít">×</button></header>
  <p><strong><?= h($smDetail['jmeno']) ?></strong><br><?= h($smDetail['pobocka']) ?> · verze <?= (int)$smDetail['verze'] ?></p>
  <?php if(!$smNoticeCurrent): ?><p class="smeny_notice">Toto oznámení nahradila novější podoba směn. Původní vyjádření zůstává v historii.</p><?php endif; ?>
  <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=me_smeny')) ?>" data-smeny-vyjadreni>
    <input type="hidden" name="action" value="smeny_potvrdit">
    <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
    <input type="hidden" name="week" value="<?= $smPublicWeekIndex ?>">
    <input type="hidden" name="id_smeny_oznameni" value="<?= (int)$smDetail['id_smeny_oznameni'] ?>">
  <?php if($smDetail['typ']==='tyden'): ?>
    <p>Týden od <?= h((new DateTimeImmutable($smNoticeContent['tyden_od']))->format('j. n. Y')) ?></p>
    <dl class="smeny_oznameni_dny">
      <?php foreach(cb_smeny_oznameni_dny($smNoticeContent) as $smNoticeDay): ?>
        <div><dt><?= h($smNoticeDay['nazev'].' '.$smNoticeDay['datum']) ?></dt><dd>
          <?php if($smNoticeDay['smeny']===[]): ?>Volno<?php else: foreach($smNoticeDay['smeny'] as $smNoticeShift): ?><span><?= h($smNoticeShift) ?></span><?php endforeach; endif; ?>
          <?php if($smNoticeWeekly && $smNoticeDay['smeny']!==[]): ?><label class="smeny_vyhrada_den"><input type="checkbox" name="vyhrady_dny[]" value="<?= h($smNoticeDay['datum_iso']) ?>"<?= in_array($smNoticeDay['datum_iso'],$smNoticeObjections,true)?' checked':'' ?><?= !$smNoticeCanReply?' disabled':'' ?>>Nevyhovuje</label><?php endif; ?>
        </dd></div>
      <?php endforeach; ?>
    </dl>
  <?php elseif($smDetail['typ']==='zmenena'):
    $smBefore=$smNoticeContent['pred'];$smAfter=$smNoticeContent['po'];
    // I změna směny přes půlnoc musí zachovat datum konce, nejen samotné hodiny.
    $smBeforeEnd=substr($smBefore['zacatek'],0,10)!==substr($smBefore['konec'],0,10)?' (do '.cb_format('d',$smBefore['konec']).')':'';
    $smAfterEnd=substr($smAfter['zacatek'],0,10)!==substr($smAfter['konec'],0,10)?' (do '.cb_format('d',$smAfter['konec']).')':'';
  ?>
    <p>Vaše směna dne <?= h((new DateTimeImmutable($smBefore['zacatek']))->format('j.n.Y')) ?><br>
      v čase <?= h(cb_format('t',$smBefore['zacatek']).'–'.cb_format('t',$smBefore['konec']).$smBeforeEnd.' ('.$smBefore['pozice'].')') ?><br>
      se změnila na<br>
      <strong><?= h((substr($smBefore['zacatek'],0,10)!==substr($smAfter['zacatek'],0,10)?cb_format('d',$smAfter['zacatek']).' ':'').cb_format('t',$smAfter['zacatek']).'–'.cb_format('t',$smAfter['konec']).$smAfterEnd.' ('.$smAfter['pozice'].')') ?></strong></p>
    <p>Prosím potvrďte, že o změně víte.</p>
  <?php else: ?><p><?= h(cb_smeny_oznameni_text($smDetail['typ'],$smNoticeContent)) ?></p><?php endif; ?>
  <?php if($smNoticeObjections!==[]): ?><p class="smeny_notice">Uložené vyjádření: Mám výhrady. Výhrada směnu automaticky neruší.</p><?php endif; ?>
  <?php if($smDetail['potvrzeno']!==null): ?>
    <p><?= $smNoticeWeekly?'Souhlas bez výhrad':'Potvrzení' ?> uloženo <?= h(cb_format('dc',$smDetail['potvrzeno'])) ?>.</p>
  <?php elseif($smNoticeCanReply): ?>
    <div class="smeny_oznameni_akce">
    <?php if($smNoticeWeekly): ?>
      <button name="vyjadreni" value="souhlas" class="smeny_primary_button"<?= $smNoticeObjections!==[]?' disabled':'' ?>>Souhlasím bez výhrad</button>
      <button name="vyjadreni" value="vyhrady" class="smeny_secondary_button"<?= $smNoticeObjections===[]?' disabled':'' ?>>Mám výhrady</button>
    <?php else: ?>
      <button name="vyjadreni" value="ok" class="smeny_primary_button"><?= $smDetail['typ']==='odebrana' || $smDetail['typ']==='tyden'?'OK':'Změnu beru na vědomí' ?></button>
    <?php endif; ?>
    </div>
  <?php elseif($smNoticeCurrent): ?><p class="smeny_notice">Potvrzování čeká na aktualizaci databáze.</p>
  <?php endif; ?>
  </form>
</dialog>
