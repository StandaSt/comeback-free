<?php
declare(strict_types=1);
/* Účel: V plánovači zobrazí žádosti vybraného týdne a nabídne vyřízení pod stávajícím zámkem pobočky. */
$smPlanCancellations=cb_smeny_zruseni_nacist($smDb,$smPlanWeek['start_day'],0,[$smPlanBranchId=>$smPlanBranch],true);
?>
<?php if($smPlanCancellations!==[]): ?>
<h2>Žádosti o zrušení směn</h2>
<?php foreach($smPlanCancellations as $smCancel): ?>
  <article class="smeny_notice">
    <strong><?= h($smCancel['jmeno'].' · '.cb_smeny_zruseni_stav($smCancel['stav'])) ?></strong>
    <p><?= h((new DateTimeImmutable($smCancel['zacatek']))->format('j. n. Y H:i').'–'.(new DateTimeImmutable($smCancel['konec']))->format('j. n. H:i').' · '.$smCancel['slot']) ?></p>
    <p>Důvod: <?= h($smCancel['poznamka']!==''?$smCancel['poznamka']:'Neuveden') ?></p>
    <?php if($smCancel['vysledek_poznamka']): ?><p>Vyjádření: <?= h($smCancel['vysledek_poznamka']) ?></p><?php endif; ?>
    <?php if($smPlanCanEdit && $smCancel['stav']==='odeslana'): ?>
      <form method="post" action="<?= h($smPlanUrl) ?>" class="smeny_cancel_form"><?php $smPlanFields(); ?>
        <input type="hidden" name="id_smeny_zruseni" value="<?= (int)$smCancel['id_smeny_zruseni'] ?>">
        <label>Vyjádření (nepovinné)<input name="vysledek_poznamka" maxlength="500"></label>
        <button name="action" value="smeny_plan_zruseni_schvalit" class="smeny_primary_button">Schválit a odebrat z pracovního plánu</button>
        <button name="action" value="smeny_plan_zruseni_zamitnout" class="smeny_secondary_button">Zamítnout</button>
      </form>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php endif; ?>
