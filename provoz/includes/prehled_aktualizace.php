<?php
declare(strict_types=1);
/* Ucel: V titulku Prehledu zobrazi posledni dokonceni aktualizace objednavek z Restie. */
require_once __DIR__ . '/../lib/aktualizace_casu_restia.php';
$cbOrdersUpdateTime = cb_aktualizace_casu_restia(db());
?>
<div class="provoz_orders_update">Aktualizace objednávek: <span data-cb-orders-update-time><?= h($cbOrdersUpdateTime !== '' ? $cbOrdersUpdateTime : '---') ?></span></div>
