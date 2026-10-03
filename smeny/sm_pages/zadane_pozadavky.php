<?php
declare(strict_types=1);
/* Účel: Zobrazí vedoucímu dostupnost a volno HPP ve vybraném týdnu, včetně historie a chybějících zadání. */
$smReadPage='zadane_pozadavky';
$smRequestPeople=cb_smeny_zadane_pozadavky($smDb,$smReadBranches,$smReadWeek['start_day']);
?>
<section class="pp smeny_content smeny_planning" data-module="smeny" data-page="zadane_pozadavky">
  <header class="pp_header"><h1>Zadané požadavky</h1></header>
  <?php if(!cb_smeny_planovani_vidi()): ?><p>Nemáte právo zobrazit požadavky ostatních zaměstnanců.</p><?php else: ?>
    <?php require __DIR__.'/tyden_filtr.php'; ?>
    <p>Požadavky na týden <?= h($smReadWeek['start']->format('j. n.').'–'.$smReadWeek['end']->format('j. n. Y')) ?>. HPP vybírá pouze den volna. Kdykoliv znamená celou provozní dobu hlavní pobočky.</p>
    <?php if($smRequestPeople===[]): ?><p>Pro vybrané pobočky nejsou dostupní pracovníci.</p><?php else: ?>
    <div class="smeny_table_scroll"><table class="smeny_overview_table"><thead><tr><th>Zaměstnanec</th><th>Zadání</th><?php foreach($smReadWeek['days'] as $smRequestDay): ?><th><?= h($smRequestDay['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach($smRequestPeople as $smRequestPerson): ?><tr>
      <th><?= h($smRequestPerson['jmeno']) ?><small><?= h($smRequestPerson['pobocka']??'Bez hlavní pobočky') ?></small></th>
      <td><?= $smRequestPerson['id_smeny_pozadavek']===null?((int)$smRequestPerson['je_hpp']===1?'HPP – volno nevybráno':'Nezadáno'):h(['hpp'=>'HPP','kdykoliv'=>'Kdykoliv','intervaly'=>'Konkrétní časy'][$smRequestPerson['rezim']]) ?><?php if($smRequestPerson['ulozeno']): ?><small><?= h((new DateTimeImmutable($smRequestPerson['ulozeno']))->format('j. n. H:i')) ?></small><?php endif; ?></td>
      <?php foreach($smReadWeek['days'] as $smDayIndex=>$smRequestDay): ?><td><?= h(cb_smeny_zadane_den($smRequestPerson,$smRequestDay,$smDayIndex+1)) ?></td><?php endforeach; ?>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  <?php endif; ?>
</section>
