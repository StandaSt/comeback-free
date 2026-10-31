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
require_once __DIR__ . '/prichody_notifikace.php';
require_once __DIR__ . '/../../common/notifikace/notifikace_info.php';
$preview = in_array('--nahled', $argv, true);
$PROSTREDI = $preview ? 'LOCAL' : 'SERVER';
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
if (!$preview && $now->format('H:i') !== '10:10') { echo "Mimo čas kontroly 10:10.\n"; exit(0); }
try {
    $db = db();
    if ($preview) { $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); }
    $branches = cb_prichody_zkontroluj($db, $now);
    $failed = cb_prichody_upozorni_zamestnance($branches, $now, $preview);
    if ($preview) { $db->rollback(); }
    echo "Kontrola příchodů 10:10 dokončena.\n";
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    if ($preview && isset($db)) { $db->rollback(); }
    if (!$preview) { cb_chyba_oznam($error, ['module' => 'PROVOZ', 'action' => 'Kontrola příchodů 10:10']); }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
