<?php
declare(strict_types=1);
/* Účel: Vykreslí jméno, týden pondělí–neděle nebo jednu změnu a souhlas s přesným obsahem zprávy. */
$smNoticeContent = json_decode($smDetail['obsah'], true, 512, JSON_THROW_ON_ERROR);
?>
<dialog class="smeny_oznameni_modal" data-smeny-oznameni-modal aria-labelledby="smeny-oznameni-title">
  <header><h2 id="smeny-oznameni-title"><?= $smDetail['typ']==='tyden'?'Zveřejněné směny':'Změna směny' ?></h2><button type="button" data-smeny-oznameni-close aria-label="Zavřít">×</button></header>
  <p><strong><?= h($smDetail['jmeno']) ?></strong><br><?= h($smDetail['pobocka']) ?></p>
  <?php if($smDetail['typ']==='tyden'): ?>
    <p>Týden od <?= h((new DateTimeImmutable($smNoticeContent['tyden_od']))->format('j. n. Y')) ?></p>
    <dl class="smeny_oznameni_dny">
      <?php foreach(cb_smeny_oznameni_dny($smNoticeContent) as $smNoticeDay): ?>
        <div><dt><?= h($smNoticeDay['nazev'].' '.$smNoticeDay['datum']) ?></dt><dd>
          <?php if($smNoticeDay['smeny']===[]): ?>Volno<?php else: foreach($smNoticeDay['smeny'] as $smNoticeShift): ?><span><?= h($smNoticeShift) ?></span><?php endforeach; endif; ?>
        </dd></div>
      <?php endforeach; ?>
    </dl>
  <?php else: ?><p><?= h(cb_smeny_oznameni_text($smDetail['typ'],$smNoticeContent)) ?></p><?php endif; ?>
  <?php if($smDetail['potvrzeno']!==null): ?>
    <p>Souhlas uložen <?= h((new DateTimeImmutable($smDetail['potvrzeno']))->format('j. n. Y H:i')) ?>.</p>
  <?php else: ?>
    <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=me_smeny')) ?>">
      <input type="hidden" name="action" value="smeny_potvrdit">
      <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
      <input type="hidden" name="week" value="<?= $smPublicWeekIndex ?>">
      <input type="hidden" name="id_smeny_oznameni" value="<?= (int)$smDetail['id_smeny_oznameni'] ?>">
      <button class="smeny_primary_button">Souhlasím</button>
    </form>
  <?php endif; ?>
</dialog>
