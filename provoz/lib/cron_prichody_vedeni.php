<?php
declare(strict_types=1);
/* Účel: CRON v 10:25 pošle aktuální souhrn stále chybějících pizzařů pouze adminovi a Vorochové. */
/* Spouštění: 25 10 * * * /cesta/k/php /cesta/k/www/provoz/lib/cron_prichody_vedeni.php
 * --nahled pouze čte lokální DB, nezapisuje ani neposílá. Běžný běh používá serverovou DB a čas Europe/Prague.
 * Kontrola nezávisí na předchozích CRONech; přihlášení kolegové ze stejného otevření se vypíší jako kontext. */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/system.php';
require_once __DIR__ . '/kontrola_prichodu.php';
require_once __DIR__ . '/prichody_notifikace.php';
require_once __DIR__ . '/../../common/notifikace/notifikace_info.php';
$preview = in_array('--nahled', $argv, true);
$PROSTREDI = $preview ? 'LOCAL' : 'SERVER';
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
if (!$preview && $now->format('H:i') !== '10:25') { echo "Mimo čas kontroly 10:25.\n"; exit(0); }
try {
    $db = db();
    if ($preview) { $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); }
    $branches = cb_prichody_zkontroluj($db, $now);
    $failed = false;
    $hasMissing = cb_prichody_souhrn($branches) !== '';
    $messages = $hasMissing ? [['prijemci' => [568, 1], 'pobocky' => $branches, 'klic' => '']] : [];
    foreach ($messages as $message) {
        $content = cb_prichody_souhrn($message['pobocky']);
        if ($preview) {
            echo 'Příjemci: ' . implode(', ', $message['prijemci']) . PHP_EOL;
            echo $content . PHP_EOL;
        } else {
            $result = cb_info_posli_jednou($message['prijemci'], 'prichody_vedeni', 'Nepřihlášený zaměstnanec', $content,
                'prichody:' . $now->format('Y-m-d') . ':1025' . $message['klic'],
                ['datum' => $now->format('Y-m-d'), 'kontrola' => '10:25', 'pobocky' => array_values($message['pobocky'])]);
            echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $failed = $failed || $result['selhalo'] > 0;
        }
    }
    if (!$messages) { echo "Žádní nepřihlášení zaměstnanci.\n"; }
    if ($preview) { $db->rollback(); }
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    if ($preview && isset($db)) { $db->rollback(); }
    if (!$preview) { cb_chyba_oznam($error, ['module' => 'PROVOZ', 'action' => 'Kontrola příchodů 10:25']); }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
