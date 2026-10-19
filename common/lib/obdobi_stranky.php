<?php
declare(strict_types=1);
/* Účel: Společný výběr období pro jednotlivé stránky; rozsahy a prodlevy žijí odděleně pouze v přihlášené relaci. */

/** Povolené kontexty jsou pevné; klient nemůže ovládat libovolnou stránku nebo rozsah dat. */
function cb_obdobi_stranky_definice(string $scope): array
{
    $definitions = [
        'provoz.objednavky' => ['block'=>'objednavky_prehled', 'days'=>false, 'default'=>'dnes', 'right'=>220],
        'provoz.top_report' => ['block'=>'top_report', 'days'=>false, 'default'=>'dnes', 'right'=>0],
        'provoz.kontrola_reportu' => ['block'=>'kontrola_reportu', 'days'=>true, 'default'=>'tyden', 'right'=>212],
    ];
    if (!isset($definitions[$scope])) throw new CbUserVisibleException('Neznámá stránka pro výběr období.');
    return $definitions[$scope];
}

/** Datum se ověřuje striktně, aby PHP potichu neopravilo například 31. únor. */
function cb_obdobi_stranky_datum(string $value): DateTimeImmutable
{
    $value = str_replace('T', ' ', trim($value));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) $value .= ' 06:00:00';
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Europe/Prague'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) throw new CbUserVisibleException('Období nemá platné datum a čas.');
    return $date;
}

/** Presety odvozujeme na serveru od provozního dne; konec intervalu je vždy výhradní. */
function cb_obdobi_stranky_rozsah(string $scope, array $input, ?DateTimeImmutable $now = null): array
{
    $def = cb_obdobi_stranky_definice($scope);
    $now = ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Europe/Prague'));
    $workday = ((int)$now->format('G') < 6 ? $now->modify('-1 day') : $now)->setTime(6, 0);
    $max = $def['days'] ? $workday : $now;
    $mode = (string)($input['mode'] ?? $def['default']);
    if (!in_array($mode, ['dnes','vcera','tyden','mesic','rok','vse','manual'], true)) throw new CbUserVisibleException('Neplatná volba období.');
    $to = $max;
    if ($mode === 'manual') {
        $from = cb_obdobi_stranky_datum((string)($input['od'] ?? ''));
        $to = cb_obdobi_stranky_datum((string)($input['do'] ?? ''));
        if ($def['days']) {
            // U celých dnů je datum Do pro uživatele včetně; SQL dostane hranici následujícího dne v 06:00.
            $from = $from->setTime(6, 0);
            $to = $to->setTime(6, 0)->modify('+1 day');
        }
        if ($from > $max || $to > $max) throw new CbUserVisibleException($def['days'] ? 'Vyberte pouze uzavřené provozní dny.' : 'Období nesmí být v budoucnosti.');
    } elseif ($mode === 'vcera') {
        $from = $workday->modify('-1 day'); $to = $workday;
    } elseif ($mode === 'tyden') {
        $from = $workday->modify('monday this week')->setTime(6, 0);
        if ($def['days']) { $to = $from; $from = $from->modify('-7 days'); }
    } elseif ($mode === 'mesic') {
        $from = $workday->modify('first day of this month')->setTime(6, 0);
    } elseif ($mode === 'rok') {
        $from = $workday->setDate((int)$workday->format('Y'), 1, 1)->setTime(6, 0);
    } elseif ($mode === 'vse') {
        $from = new DateTimeImmutable('2000-01-01 06:00:00', new DateTimeZone('Europe/Prague'));
    } else {
        $from = $workday;
    }
    // Přesně v 06:00 je nový provozní den zatím prázdný, jeho výchozí přehled se však musí otevřít.
    $emptyCurrentDay = !$def['days'] && $mode === 'dnes' && $from == $to;
    if ($from < new DateTimeImmutable('2000-01-01', new DateTimeZone('Europe/Prague')) || ($from >= $to && !$emptyCurrentDay)) throw new CbUserVisibleException('Začátek období musí být dříve než konec a období musí obsahovat dostupný provozní den.');
    $displayTo = $def['days'] ? $to->modify('-1 day') : $to;
    return [
        'scope'=>$scope, 'od'=>$from->format('Y-m-d H:i:s'), 'do'=>$to->format('Y-m-d H:i:s'), 'mode'=>$mode,
        'input_od'=>$from->format('Y-m-d'), 'input_do'=>$displayTo->format('Y-m-d'),
        'time_od'=>$from->format('H:i'), 'time_do'=>$to->format('H:i'),
        'max_date'=>($def['days'] ? $max->modify('-1 day') : $max)->format('Y-m-d'),
        'label'=>$from->format($def['days'] ? 'j. n. Y' : 'j. n. Y H:i') . ' – ' . $displayTo->format($def['days'] ? 'j. n. Y' : 'j. n. Y H:i'),
        'days'=>$def['days'], 'block'=>$def['block'],
    ];
}

/** Zastaralý nebo poškozený stav jedné stránky nesmí omezovat jiné stránky ani blokovat jejich načtení. */
function cb_obdobi_stranky_model(string $scope, ?DateTimeImmutable $now = null): array
{
    $saved = $_SESSION['cb_obdobi_stranek'][$scope] ?? [];
    try { $model = cb_obdobi_stranky_rozsah($scope, is_array($saved) ? $saved : [], $now); }
    catch (CbUserVisibleException $error) { $model = cb_obdobi_stranky_rozsah($scope, [], $now); }
    $delay = (int)($saved['delay'] ?? 3000);
    $model['delay'] = in_array($delay, range(1000,10000,1000), true) ? $delay : 3000;
    return $model;
}

/** Oprávnění se kontroluje i při samostatném AJAX požadavku, nezávisle na viditelnosti tlačítka. */
function cb_obdobi_stranky_pristup(string $scope): bool
{
    $def = cb_obdobi_stranky_definice($scope);
    return !empty($_SESSION['login_ok']) && (int)($_SESSION['cb_user']['id_user'] ?? 0) > 0
        && cb_modul_ma_pristup('provoz') && ($def['right'] === 0
            || (array_key_exists($def['right'], $_SESSION['prava_stav'] ?? []) && cb_pravo_ma($def['right'])));
}

/** Po validaci mění jen zvolený kontext; přesná nová i předchozí hodnota se předá stávajícímu auditu. */
function cb_obdobi_stranky_ulozit(array $input): array
{
    $scope = (string)($input['scope'] ?? '');
    if (!cb_obdobi_stranky_pristup($scope)) throw new CbUserVisibleException('Nemáte právo měnit období této stránky.');
    if (!in_array($input['action'] ?? '', ['', 'delay'], true)) throw new CbUserVisibleException('Neplatná akce výběru období.');
    $old = cb_obdobi_stranky_model($scope);
    if (($input['action'] ?? '') === 'delay') {
        $delay = filter_var($input['delay'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($delay, range(1000,10000,1000), true)) throw new CbUserVisibleException('Prodleva musí být v rozsahu 1 až 10 sekund.');
        $_SESSION['cb_obdobi_stranek'][$scope]['delay'] = $delay;
        return cb_obdobi_stranky_model($scope);
    }
    $model = cb_obdobi_stranky_rozsah($scope, $input);
    $saved = ['mode'=>$model['mode'], 'od'=>$model['od'], 'do'=>$model['days'] ? $model['input_do'] : $model['do'], 'delay'=>$old['delay']];
    if (!cb_user_akce_zapis(['id_user_akce_typ'=>3, 'modul'=>'provoz', 'zdroj'=>'obdobi_stranky', 'objekt'=>$scope,
        'hodnota_old'=>$old['od'].' – '.$old['do'], 'hodnota_new'=>$model['od'].' – '.$model['do'],
        'detail'=>['scope'=>$scope,'mode'=>$model['mode'],'od'=>$model['od'],'do'=>$model['do']]])) throw new RuntimeException('Nepodařilo se zaznamenat změnu období.');
    $_SESSION['cb_obdobi_stranek'][$scope] = $saved;
    return cb_obdobi_stranky_model($scope);
}
