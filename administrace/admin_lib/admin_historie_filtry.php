<?php
declare(strict_types=1);
/* Účel: Ověří filtry historie činnosti a sestaví odkazy se zachováním zvoleného rozsahu. */

/** Období je omezené na rok; neplatný vstup nesmí spustit neomezený audit. */
function cb_historie_filtry(array $input): array
{
    $today = new DateTimeImmutable('today');
    $dates = [];
    foreach (['od'=>$today->modify('-29 days')->format('Y-m-d'), 'do'=>$today->format('Y-m-d')] as $key=>$default) {
        $value = $input['hc_'.$key] ?? $default;
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) throw new CbUserVisibleException('Vyberte platné období.');
        $dates[$key] = $date;
    }
    if ($dates['do'] < $dates['od'] || $dates['od']->diff($dates['do'])->days > 365) throw new CbUserVisibleException('Vyberte období nejvýše jednoho roku.');
    $out = ['od'=>$dates['od']->format('Y-m-d'), 'do'=>$dates['do']->format('Y-m-d'), 'konec'=>$dates['do']->modify('+1 day')->format('Y-m-d')];
    foreach (['autor','osoba','pobocka','modul'] as $key) {
        $raw = $input['hc_'.$key] ?? '0';
        if (!is_scalar($raw) || !ctype_digit((string)$raw) || (float)$raw > 2147483647) throw new CbUserVisibleException('Neplatný filtr historie.');
        $out[$key] = (int)$raw;
    }
    $out['zdroj'] = is_string($input['hc_zdroj'] ?? null) ? $input['hc_zdroj'] : '';
    $out['druh'] = in_array($input['hc_druh'] ?? '', ['vse','vecne','chyby'], true) ? $input['hc_druh'] : 'vecne';
    $out['pohled'] = in_array($input['hc_pohled'] ?? '', ['uzivatel','agenda','zaznam'], true) ? $input['hc_pohled'] : 'uzivatel';
    $out['datum'] = is_string($input['hc_datum'] ?? null) ? $input['hc_datum'] : '';
    if ($out['datum'] !== '') {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $out['datum']);
        if (!$day || $day->format('Y-m-d') !== $out['datum']) throw new CbUserVisibleException('Vyberte platný den reportu.');
    }
    $out['strana'] = max(1, min(100, (int)($input['hc_strana'] ?? 1)));
    $out['pocet'] = in_array((int)($input['hc_pocet'] ?? 50), [25,50,100], true) ? (int)($input['hc_pocet'] ?? 50) : 50;
    return $out;
}

/** Odkaz neobsahuje technické interní hodnoty modelu filtru. */
function cb_historie_url(array $filters, array $extra = []): string
{
    $params = ['m'=>'administrace','page'=>'historie_cinnosti'];
    foreach (['od','do','autor','osoba','pobocka','modul','zdroj','pohled','datum','strana','pocet','druh'] as $key) $params['hc_'.$key] = $filters[$key];
    return cb_root_url('index.php?'.http_build_query(array_replace($params, $extra), '', '&', PHP_QUERY_RFC3986));
}
