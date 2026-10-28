<?php
declare(strict_types=1);
/* Účel: Připraví čtecí model historie a předá jej kompaktnímu pohledu; přímý přístup kontroluje stejně jako menu. */
require_once __DIR__.'/../admin_lib/admin_historie_pristup.php';
if (!cb_historie_pravo(111)) {
    http_response_code(403);
    echo '<p>Nemáte právo zobrazit historii činnosti.</p>';
    return;
}
require_once __DIR__.'/../admin_lib/admin_historie_filtry.php';
require_once __DIR__.'/../admin_lib/admin_historie_popis.php';
require_once __DIR__.'/../admin_db/admin_historie_db.php';
require_once __DIR__.'/../admin_db/admin_historie_detail.php';
try {
    $hcDb = db(); $hcFilters = cb_historie_filtry($_GET); $hcScope = cb_historie_rozsah($hcDb);
    $hcSources = cb_historie_zdroje($hcDb, $hcScope);
    if ($hcFilters['zdroj'] !== '' && !isset($hcSources[$hcFilters['zdroj']])) throw new CbUserVisibleException('Zvolený zdroj historie není dostupný.');
    $hcNames = cb_historie_jmena($hcDb, $hcScope);
    $hcModules = $hcDb->query('SELECT id_modul,modul FROM cis_moduly ORDER BY poradi,id_modul')->fetch_all(MYSQLI_ASSOC);
    $hcRows = cb_historie_cti($hcDb, $hcSources, $hcFilters);
    $hcMore = count($hcRows) > $hcFilters['pocet']; $hcRows = array_slice($hcRows,0,$hcFilters['pocet']);
    $hcDetail = null;
    $hcDetailKey = is_string($_GET['hc_detail'] ?? null) ? $_GET['hc_detail'] : '';
    if ($hcDetailKey !== '') {
        if (!preg_match('/^([a-z_]+):([1-9][0-9]{0,17})$/D', $hcDetailKey, $hcMatch) || !isset($hcSources[$hcMatch[1]])) throw new CbUserVisibleException('Neplatný detail historie.');
        $hcEvents = cb_historie_cti($hcDb, $hcSources, $hcFilters, ['zdroj'=>$hcMatch[1],'id'=>$hcMatch[2]]);
        if ($hcEvents === []) throw new CbUserVisibleException('Událost neexistuje nebo k ní nemáte přístup.');
        $hcDetail = ['event'=>$hcEvents[0], 'data'=>cb_historie_detail($hcDb,$hcEvents[0],$hcSources[$hcMatch[1]],$hcScope)];
    }
    require __DIR__.'/../admin_includes/admin_historie_prehled.php';
} catch (Throwable $error) {
    echo '<p class="txt_cervena">'.h(cb_admin_chyba_text($error,'Čtení historie činnosti')).'</p>';
}
