<?php
declare(strict_types=1);
/* Účel: Vlastní archiv sdělení pro přihlášeného příjemce, společný všem modulům. */
require_once __DIR__.'/../lib/admin_sdeleni.php';
$sdPage=max(0,(int)($_GET['sd_strana']??0));
$sdRows=cb_sdeleni_osobni(db(),cb_sdeleni_user(),true,$sdPage*50);
$sdMore=count($sdRows)>50;$sdRows=array_slice($sdRows,0,50);
$sdModule=(string)($GLOBALS['CURRENT_MODULE']??'provoz');
$sdBase='index.php?m='.rawurlencode($sdModule).'&page=sdeleni_archiv';
?>
<section class="pp sd_archive_page" data-module="<?= h($sdModule) ?>" data-page="sdeleni_archiv">
    <header class="pp_header"><h1>Info od admina</h1></header>
    <?php if ($sdRows===[]): ?><p>Nemáte žádná sdělení.</p><?php endif; ?>
    <?php foreach ($sdRows as $sd): ?>
        <article class="sd_archive_item">
            <strong data-sdeleni-archiv-id="<?= (int)$sd['id_sdeleni'] ?>" data-sdeleni-zobrazeno="<?= $sd['zobrazeno']?'1':'0' ?>"><?= h($sd['nadpis']) ?></strong>
            <p class="sd_text"><?= h($sd['obsah']) ?></p>
            <p class="sd_meta"><?= h($sd['autor']?:'Uživatel #'.$sd['id_user_vytvoril']) ?> · <?= h(cb_sdeleni_cas($sd['odeslano'])) ?> · Platnost do <?= h(cb_sdeleni_cas($sd['platnost_do'])) ?></p>
            <p class="sd_meta">Zobrazeno: <?= h(cb_sdeleni_cas($sd['zobrazeno'])) ?> · Přečteno: <?= h(cb_sdeleni_cas($sd['precteno'])) ?></p>
        </article>
    <?php endforeach; ?>
    <nav class="sd_pagination" aria-label="Stránky archivu">
        <?php if ($sdPage>0): ?><a href="<?= h(cb_root_url($sdBase.'&sd_strana='.($sdPage-1))) ?>">Novější</a><?php endif; ?>
        <?php if ($sdMore): ?><a href="<?= h(cb_root_url($sdBase.'&sd_strana='.($sdPage+1))) ?>">Starší</a><?php endif; ?>
    </nav>
</section>
