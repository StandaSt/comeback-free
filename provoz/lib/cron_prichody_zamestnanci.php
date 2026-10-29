<?php
declare(strict_types=1);
/* Účel: CRON v 10:10 pošle každému chybějícímu pizzaři od 10:00 vlastní výzvu k přihlášení na pobočkovém PC. */
/* Spouštění: 10 10 * * * /cesta/k/php /cesta/k/www/provoz/lib/cron_prichody_zamestnanci.php
 * --nahled pouze čte lokální DB a vypisuje vyhodnocení, nic neukládá a neposílá.
 * Bez --nahled běží pouze v 10:10 Europe/Prague a používá serverové připojení jako ostatní provozní CRONy. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/system.php';
require_once __DIR__ . '/kontrola_prichodu.php';
require_once __DIR__ . '/../../common/notifikace/notifikace_info.php';
$preview = in_array('--nahled', $argv, true);
$PROSTREDI = $preview ? 'LOCAL' : 'SERVER';
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
if (!$preview && $now->format('H:i') !== '10:10') { echo "Mimo čas kontroly 10:10.\n"; exit(0); }
try {
    $db = db();
    if ($preview) { $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); }
    $branches = cb_prichody_zkontroluj($db, $now);
    $failed = false;
    foreach ($branches as $branch) {
        if (!$branch['overena']) {
            $error = new RuntimeException('Pobočka ' . $branch['nazev'] . ' nemá potvrzený PC. Přítomnost nelze vyhodnotit.');
            if (!$preview) { cb_chyba_oznam($error, ['module' => 'PROVOZ', 'action' => 'Kontrola příchodů 10:10']); }
            fwrite(STDERR, $error->getMessage() . PHP_EOL);
            $failed = true;
            continue;
        }
        foreach ($branch['neprihlaseni'] as $person) {
            if ($preview) { echo $branch['nazev'] . ': nepřihlášen ' . $person['jmeno'] . PHP_EOL; continue; }
            $result = cb_info_posli_jednou([(int)$person['id_user']], 'prichody_zamestnanec', 'Přihlašte se do IS',
                "Není evidováno Vaše přihlášení do IS, udělejte to prosím co nejdříve.\nDěkuji",
                'prichody:' . $now->format('Y-m-d') . ':1010:' . $person['id_user'],
                ['datum' => $now->format('Y-m-d'), 'kontrola' => '10:10', 'id_pob' => $branch['id_pob'], 'zamestnanec' => $person]);
            echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $failed = $failed || $result['selhalo'] > 0;
        }
    }
    if ($preview) { $db->rollback(); }
    echo "Kontrola příchodů 10:10 dokončena.\n";
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    if ($preview && isset($db)) { $db->rollback(); }
    if (!$preview) { cb_chyba_oznam($error, ['module' => 'PROVOZ', 'action' => 'Kontrola příchodů 10:10']); }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
