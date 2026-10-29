<?php
declare(strict_types=1);
/* Účel: CRON v 10:15 pošle souhrn chybějících pizzařů Vorochové a adminovi, restaurant managerům jen jejich pobočky. */
/* Spouštění: 15 10 * * * /cesta/k/php /cesta/k/www/provoz/lib/cron_prichody_vedeni.php
 * --nahled pouze čte lokální DB, nezapisuje ani neposílá. Běžný běh používá serverovou DB a čas Europe/Prague.
 * Kontrola nezávisí na prvním CRONu; přihlášení kolegové ze stejného otevření se vypíší jako kontext. */

/** Text zprávy pro vedení patří tomuto CRONu; společná kontrola žádné notifikace nesestavuje. */
function cb_prichody_souhrn(array $branches): string
{
    $lines = [];
    foreach ($branches as $branch) {
        if (!$branch['overena'] || !$branch['neprihlaseni']) { continue; }
        $lines[] = 'Směna: ' . $branch['nazev'];
        foreach ($branch['neprihlaseni'] as $person) { $lines[] = $person['jmeno'] . ' – nepřihlášen'; }
        foreach ($branch['prihlaseni'] as $person) { $lines[] = $person['jmeno'] . ' – přihlášen'; }
        $lines[] = '';
    }
    return $lines ? implode("\n", $lines) . "Zaměstnanec se nepřihlásil do IS,\nprosím ověřte jeho přítomnost na pracovišti." : '';
}

/** Restaurant manager je funkce ID 4, nikoliv celá role 5 zahrnující i assistant managery. Vazbu určuje platné MAIN. */
function cb_prichody_restaurant_manageri(mysqli $db, string $date): array
{
    require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';
    $stmt = $db->prepare('SELECT DISTINCT m.id_pob, p.id_user
        FROM ' . cb_hr_main_sql($db) . ' m
        JOIN hr_person p ON p.id_person=m.id_person AND p.aktivni=1 AND p.testovaci=0
        JOIN `user` u ON u.id_user=p.id_user
        JOIN pobocka b ON b.id_pob=m.id_pob AND b.id_firma=p.id_firma AND b.aktivni=1
        JOIN hr_funkce f ON f.id_person=p.id_person AND f.id_funkce=4 AND f.platny=1
        JOIN cis_funkce cf ON cf.id_funkce=f.id_funkce AND cf.aktivni=1
        WHERE ' . cb_hr_ucinne_sql('m', '?') . '
            AND (f.platnost_od IS NULL OR f.platnost_od<=?)
            AND (f.platnost_do IS NULL OR f.platnost_do>=?)
        ORDER BY m.id_pob, p.id_user');
    $stmt->bind_param('ssss', $date, $date, $date, $date);
    $stmt->execute();
    $managers = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $managers[(int)$row['id_pob']][(int)$row['id_user']] = (int)$row['id_user'];
    }
    $stmt->close();
    return $managers;
}

/** Pevní příjemci mají celý souhrn. Vedoucí jen vlastní pobočky a žádnou další zprávu, pokud sami chybí od 10:00. */
function cb_prichody_zpravy_vedeni(array $branches, array $managers): array
{
    $affected = [];
    $missingUsers = [];
    foreach ($branches as $id => $branch) {
        if (!$branch['overena'] || !$branch['neprihlaseni']) { continue; }
        $affected[$id] = $branch;
        foreach ($branch['neprihlaseni'] as $person) { $missingUsers[(int)$person['id_user']] = true; }
    }
    if (!$affected) { return []; }
    $messages = [['prijemci' => [568, 1], 'pobocky' => $affected, 'klic' => '']];
    $byManager = [];
    foreach ($affected as $id => $branch) {
        foreach ($managers[(int)$branch['id_pob']] ?? [] as $user) {
            $user = (int)$user;
            if ($user <= 0 || isset($missingUsers[$user]) || in_array($user, [568, 1], true)) { continue; }
            $byManager[$user][$id] = $branch;
        }
    }
    // Více vlastních poboček vedoucí dostane v jediné zprávě; cizí pobočky se do ní nedostanou.
    foreach ($byManager as $user => $ownBranches) {
        $messages[] = ['prijemci' => [(int)$user], 'pobocky' => $ownBranches, 'klic' => ':vedouci:' . $user];
    }
    return $messages;
}

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/system.php';
require_once __DIR__ . '/kontrola_prichodu.php';
require_once __DIR__ . '/../../common/notifikace/notifikace_info.php';
$preview = in_array('--nahled', $argv, true);
$PROSTREDI = $preview ? 'LOCAL' : 'SERVER';
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
if (!$preview && $now->format('H:i') !== '10:15') { echo "Mimo čas kontroly 10:15.\n"; exit(0); }
try {
    $db = db();
    if ($preview) { $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); }
    $branches = cb_prichody_zkontroluj($db, $now);
    $failed = false;
    foreach ($branches as $branch) {
        if (!$branch['overena']) {
            $error = new RuntimeException('Pobočka ' . $branch['nazev'] . ' nemá potvrzený PC. Přítomnost nelze vyhodnotit.');
            if (!$preview) { cb_chyba_oznam($error, ['module' => 'PROVOZ', 'action' => 'Kontrola příchodů 10:15']); }
            fwrite(STDERR, $error->getMessage() . PHP_EOL);
            $failed = true;
        }
    }
    $hasMissing = cb_prichody_souhrn($branches) !== '';
    $managers = $hasMissing ? cb_prichody_restaurant_manageri($db, $now->format('Y-m-d')) : [];
    $messages = cb_prichody_zpravy_vedeni($branches, $managers);
    foreach ($messages as $message) {
        $content = cb_prichody_souhrn($message['pobocky']);
        if ($preview) {
            echo 'Příjemci: ' . implode(', ', $message['prijemci']) . PHP_EOL;
            echo $content . PHP_EOL;
        } else {
            $result = cb_info_posli_jednou($message['prijemci'], 'prichody_vedeni', 'Nepřihlášený zaměstnanec', $content,
                'prichody:' . $now->format('Y-m-d') . ':1015' . $message['klic'],
                ['datum' => $now->format('Y-m-d'), 'kontrola' => '10:15', 'pobocky' => array_values($message['pobocky'])]);
            echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $failed = $failed || $result['selhalo'] > 0;
        }
    }
    if (!$messages) { echo "Žádní prokazatelně nepřihlášení zaměstnanci.\n"; }
    if ($preview) { $db->rollback(); }
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    if ($preview && isset($db)) { $db->rollback(); }
    if (!$preview) { cb_chyba_oznam($error, ['module' => 'PROVOZ', 'action' => 'Kontrola příchodů 10:15']); }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
