<?php
declare(strict_types=1);
/* Účel: Sestavení notifikací příchodů a výběr vedoucích poboček; čas a stupeň eskalace určují jednoúčelové CRONy. */

/** Souhrn pro vedoucí i vedení obsahuje chybějící zaměstnance a přihlášené kolegy ze stejného otevření. */
function cb_prichody_souhrn(array $branches): string
{
    $lines = [];
    foreach ($branches as $branch) {
        if (!$branch['neprihlaseni']) { continue; }
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

/** Vedoucí dostane souhrn vlastních poboček i tehdy, když sám chybí a zároveň dostává osobní výzvu. */
function cb_prichody_zpravy_vedouci(array $branches, array $managers): array
{
    $affected = [];
    foreach ($branches as $id => $branch) {
        if (!$branch['neprihlaseni']) { continue; }
        $affected[$id] = $branch;
    }
    if (!$affected) { return []; }
    $messages = [];
    $byManager = [];
    foreach ($affected as $id => $branch) {
        foreach ($managers[(int)$branch['id_pob']] ?? [] as $user) {
            $user = (int)$user;
            if ($user <= 0) { continue; }
            $byManager[$user][$id] = $branch;
        }
    }
    // Více vlastních poboček vedoucí dostane v jediné zprávě; cizí pobočky se do ní nedostanou.
    foreach ($byManager as $user => $ownBranches) {
        $messages[] = ['prijemci' => [(int)$user], 'pobocky' => $ownBranches, 'klic' => ':vedouci:' . $user];
    }
    return $messages;
}

/** Každý chybějící zaměstnanec dostane vlastní výzvu; čas v klíči dovolí opakování v 10:15, ale zabrání duplicitě stejného běhu. */
function cb_prichody_upozorni_zamestnance(array $branches, DateTimeImmutable $now, bool $preview): bool
{
    $failed = false;
    $time = $now->format('H:i');
    foreach ($branches as $branch) {
        // Bez potvrzeného PC nelze doložit příchod, zaměstnanec ale nesmí z upozornění vypadnout.
        foreach ($branch['neprihlaseni'] as $person) {
            if ($preview) { echo $branch['nazev'] . ': nepřihlášen ' . $person['jmeno'] . PHP_EOL; continue; }
            $result = cb_info_posli_jednou([(int)$person['id_user']], 'prichody_zamestnanec', 'Přihlašte se do IS',
                "Není evidováno Vaše přihlášení do IS, udělejte to prosím co nejdříve.\nDěkuji",
                'prichody:' . $now->format('Y-m-d') . ':' . $now->format('Hi') . ':' . $person['id_user'],
                ['datum' => $now->format('Y-m-d'), 'kontrola' => $time, 'id_pob' => $branch['id_pob'], 'zamestnanec' => $person]);
            echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $failed = $failed || $result['selhalo'] > 0;
        }
    }
    return $failed;
}
