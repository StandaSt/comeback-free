<?php
declare(strict_types=1);
/* Účel: Zobrazí zaměstnanci stav jeho žádostí v daném týdnu a umožní stáhnout dosud nevyřizovanou žádost. */
?>
<h2>Moje žádosti o zrušení</h2>
<p>Do zveřejnění zrušení směna stále platí. Žádosti lze podávat pouze k interním směnám v IS.</p>
<?php if($smOwnCancellations===[]): ?><p>V tomto týdnu nemáte žádnou žádost.</p><?php endif; ?>
<?php foreach($smOwnCancellations as $smCancel): ?>
  <article class="smeny_notice">
    <strong><?= h($smCancel['pobocka'].' · '.cb_smeny_zruseni_stav($smCancel['stav'])) ?></strong>
    <p><?= h((new DateTimeImmutable($smCancel['zacatek']))->format('j. n. Y H:i').'–'.(new DateTimeImmutable($smCancel['konec']))->format('j. n. H:i').' · '.$smCancel['slot']) ?></p>
    <p>Důvod žádosti: <?= h($smCancel['poznamka']!==''?$smCancel['poznamka']:'Neuveden') ?></p>
    <?php if($smCancel['vysledek_poznamka']): ?><p>Vyjádření vedoucího: <?= h($smCancel['vysledek_poznamka']) ?></p><?php endif; ?>
    <?php if($smCancel['stav']==='odeslana'): ?><form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=me_smeny')) ?>">
      <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="week" value="<?= $smPublicWeekIndex ?>"><input type="hidden" name="id_smeny_zruseni" value="<?= (int)$smCancel['id_smeny_zruseni'] ?>">
      <button class="smeny_secondary_button" name="action" value="smeny_zruseni_stahnout">Stáhnout žádost</button>
    </form><?php endif; ?>
  </article>
<?php endforeach; ?>
