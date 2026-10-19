<?php
declare(strict_types=1);

// Titulek objednavek s mistnim obdobim a aktualizaci Restie vedle sebe.
?>
<div class="provoz_objednavky_header_title">
    <h1><?= h($cbProvozPageTitle) ?></h1>
    <div class="provoz_objednavky_header_controls" data-period-block-target="objednavky_prehled">
        <?php $cbPeriodScope = 'provoz.objednavky'; require __DIR__ . '/../../common/includes/vyber_obdobi.php'; ?>
        <button type="button" class="head_task_btn head_task_btn--restia-refresh" data-objednavky-restia-refresh>Aktualizace objednávek</button>
    </div>
</div>
