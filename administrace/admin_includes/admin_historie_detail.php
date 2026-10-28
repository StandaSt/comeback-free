<?php
declare(strict_types=1);
/* Účel: Zobrazí autorizovaný detail jedné události a oddělí podklad od dosud neevidované historie změn. */
$event = $hcDetail['event']; $data = $hcDetail['data']; $description = cb_historie_popis($event);
?>
<section class="hc_detail" id="hc-detail" aria-label="Detail události">
    <header><strong><?= h($description['nazev']) ?></strong><a href="<?= h(cb_historie_url($hcFilters)) ?>">Zavřít detail</a></header>
    <p><?= h(cb_format('dcs',$event['cas'])) ?> · <?= h((int)$event['autor']>0?($hcNames['autori'][(int)$event['autor']]??'Uživatel').' · #'.$event['autor']:'Autor nedoložen') ?> · <?= h($hcSources[$event['zdroj']]['label']) ?> #<?= h((string)$event['id']) ?></p>
    <?php if ($data['fields']!==[]): ?><dl><?php foreach ($data['fields'] as $label=>$value): ?><div><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    <?php if ($data['note']!==''): ?><p class="hc_poznamka"><?= h($data['note']) ?></p><?php endif; ?>
    <?php if ($data['link']!==''): ?><a href="<?= h($data['link']) ?>"><?= h(match($event['zdroj']){'reporty'=>'Otevřít aktuální report tohoto dne','chyby'=>'Otevřít přehled chyb',default=>'Otevřít zaměstnance'}) ?></a><?php endif; ?>
</section>
