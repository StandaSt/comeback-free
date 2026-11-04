<?php
declare(strict_types=1);
/* Účel: Připravuje nový přehled činnosti z jednotné evidence; první zapojenou agendou je Helpdesk. */
if (!isset($_SESSION['prava_stav'][111]) || !cb_pravo_ma(111)) {
    http_response_code(403);
    echo '<p>Nemáte právo zobrazit historii činnosti.</p>';
    return;
}
require_once __DIR__.'/../admin_lib/admin_cinnost.php';
require_once __DIR__.'/../admin_db/admin_cinnost_db.php';
try {
    $ncFilters=cb_admin_cinnost_filtry($_GET);
    $ncData=cb_admin_cinnost_nacist(db(),$ncFilters);
    require __DIR__.'/../admin_includes/admin_cinnost_prehled.php';
} catch (Throwable $error) {
    echo '<p class="txt_cervena">'.h(cb_admin_chyba_text($error,'Čtení nové historie činnosti')).'</p>';
}
