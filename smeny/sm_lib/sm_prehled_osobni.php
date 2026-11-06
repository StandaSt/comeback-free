<?php
declare(strict_types=1);
/* Účel: Sestaví skutečné hodiny z platných reportů IS a týdny s reporty minulých provozních dnů a interním plánem aktuálních i budoucích dnů. */

/** Praha určuje kalendářní součty; provozní den se stejně jako denní report překlápí v 06:00. */
function cb_smeny_prehled_obdobi(?DateTimeImmutable $now = null): array
{
    $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->setTimezone(new DateTimeZone('Europe/Prague'));
    $monday = $now->modify('monday this week')->setTime(0,0);
    $month = $now->modify('first day of this month')->setTime(0,0);
    return ['now'=>$now, 'workday'=>$now->modify('-6 hours')->format('Y-m-d'),
        'week'=>$monday, 'next'=>$monday->modify('+7 days'), 'end'=>$monday->modify('+14 days'),
        'month'=>$month, 'previous'=>$month->modify('-1 month')];
}

/** Report patří celým evidovaným výkonem k datu reportu; hodiny se nepřepočítávají z intervalu ani z plánu. */
function cb_smeny_prehled_hodiny(array $reports, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $totals = [];
    $first = $from->format('Y-m-d');
    $last = $to->format('Y-m-d');
    foreach ($reports as $row) {
        if ($row['datum'] < $first || $row['datum'] >= $last) continue;
        $slot = (int)$row['id_slot'];
        $totals[$slot] ??= ['slot'=>$row['slot'], 'hodiny'=>0.0];
        $totals[$slot]['hodiny'] += (float)$row['odpracovano'];
    }
    ksort($totals);
    return $totals;
}

/** Minulé provozní dny čtou pouze reporty; chybějící evidence se nikdy nedopočítává z plánovaných směn. */
function cb_smeny_prehled_dny(array $plans, DateTimeImmutable $start, array $reports, string $workday): array
{
    $names = ['Pondělí','Úterý','Středa','Čtvrtek','Pátek','Sobota','Neděle'];
    $days = [];
    foreach ($names as $index=>$name) {
        $date = $start->modify('+'.$index.' days');
        $key = $date->format('Y-m-d');
        $closed = $key < $workday;
        $rows = $closed
            ? array_values(array_filter($reports, static fn(array $r): bool => $r['datum']===$key))
            : array_values(array_filter($plans, static fn(array $r): bool => $r['tyden_od']===$start->format('Y-m-d') && $r['datum']===$key));
        $shifts = [];
        foreach ($rows as $row) {
            $from = $closed ? $row['smena_od'] : substr($row['zacatek'],11,5);
            $to = $closed ? $row['smena_do'] : substr($row['konec'],11,5);
            $shifts[] = ['cas_od'=>$from===null?'':substr($from,0,5), 'cas_do'=>$to===null?'':substr($to,0,5),
                'slot'=>$row['slot'], 'pobocka'=>$row['pobocka'], 'hodiny'=>$closed?(float)$row['odpracovano']:null];
        }
        $days[] = ['name'=>$name, 'date'=>$date, 'report'=>$closed, 'smeny'=>$shifts];
    }
    return $days;
}

/** Čisté sestavení společné pro aplikaci i izolované ověření kombinace reportů a plánů. */
function cb_smeny_prehled_osobni_sestavit(array $reports, array $plans, array $period): array
{
    $groups = [];
    foreach ([['Tento týden',$period['week'],$period['next']], ['Tento měsíc',$period['month'],$period['month']->modify('+1 month')],
        ['Minulý měsíc',$period['previous'],$period['month']]] as $index=>[$label,$start,$end]) {
        $groups[] = ['label'=>$label, 'from'=>$start, 'to'=>$end->modify('-1 day'),
            'sloty'=>cb_smeny_prehled_hodiny($reports,$start,$end), 'detail'=>$index===0,
            'evidence'=>$index===0?array_values(array_filter($reports,static fn(array $r):bool=>$r['datum'] >= $start->format('Y-m-d') && $r['datum'] < $end->format('Y-m-d'))):[]];
    }
    $nextDays = cb_smeny_prehled_dny($plans,$period['next'],[],$period['workday']);
    $nextPlanned = array_filter($nextDays,static fn(array $day):bool=>$day['smeny']!==[] )!==[];
    $weeks = [
        ['label'=>'Směny tento týden','from'=>$period['week'],'to'=>$period['next']->modify('-1 day'),
            'planned'=>true, 'days'=>cb_smeny_prehled_dny($plans,$period['week'],$reports,$period['workday'])],
        ['label'=>'Směny následující týden','from'=>$period['next'],'to'=>$period['end']->modify('-1 day'),
            'planned'=>$nextPlanned,'days'=>$nextDays],
    ];
    return ['hodiny'=>$groups, 'tydny'=>$weeks];
}

/** Součty načte z finálních reportů IS, budoucnost ze zveřejněného interního plánu; identita je vždy serverová. */
function cb_smeny_prehled_osobni(mysqli $db, int $person, ?DateTimeImmutable $now = null): array
{
    $period = cb_smeny_prehled_obdobi($now);
    $from = min($period['previous'], $period['week']);
    $reports = cb_smeny_prehled_osobni_reporty($db,$person,$from->format('Y-m-d'),
        (new DateTimeImmutable($period['workday'],new DateTimeZone('Europe/Prague')))->modify('+1 day')->format('Y-m-d'));
    $plans = cb_smeny_prehled_osobni_smeny($db,$person,$period['week']->format('Y-m-d H:i:s'),$period['end']->modify('+1 day')->format('Y-m-d H:i:s'));
    return cb_smeny_prehled_osobni_sestavit($reports,$plans,$period);
}
