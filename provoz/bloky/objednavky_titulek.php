<?php
declare(strict_types=1);

// Titulek objednavek s filtrem provozniho dne napojenym na formular tabulky.
require_once __DIR__ . '/../db/db_objednavky_prehled.php';
$objednavkyReportFilter = cb_db_objednavky_prehled_report_filter();
?>
<div class="provoz_objednavky_header_title">
    <h1><?= h($cbProvozPageTitle) ?></h1>
    <label class="provoz_objednavky_report_filter" for="obj_report">Provozní den
        <input id="obj_report" type="date" name="obj_f[report]" form="provoz_objednavky_form" class="filter-input" value="<?= h($objednavkyReportFilter) ?>">
    </label>
    <button type="button" class="head_task_btn head_task_btn--restia-refresh" data-objednavky-restia-refresh>Aktualizace objednávek</button>
</div>
