<?php
declare(strict_types=1);
/* Účel: Ověří filtry historie činnosti; výchozí přehled zahrnuje všechny uživatelské akce a odkazy zachovávají zvolený rozsah. */

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
    $out['druh'] = in_array($input['hc_druh'] ?? '', ['vse','vecne','chyby'], true) ? $input['hc_druh'] : 'vse';
    $out['pohled'] = in_array($input['hc_pohled'] ?? '', ['uzivatel','agenda','zaznam'], true) ? $input['hc_pohled'] : 'uzivatel';
    $out['datum'] = is_string($input['hc_datum'] ?? null) ? $input['hc_datum'] : '';
    if ($out['datum'] !== '') {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $out['datum']);
        if (!$day || $day->format('Y-m-d') !== $out['datum']) throw new CbUserVisibleException('Vyberte platný den reportu.');
    }
    $out['strana'] = max(1, min(2147483647, (int)($input['hc_p'] ?? $input['hc_strana'] ?? 1)));
    $out['pocet'] = in_array((int)($input['hc_per'] ?? $input['hc_pocet'] ?? 50), [25,50,100], true) ? (int)($input['hc_per'] ?? $input['hc_pocet'] ?? 50) : 50;
    // Sloupcové filtry se vždy vyhodnocují před stránkováním, nikoli jen v zobrazené stránce.
    $columns = $input['hc_f'] ?? [];
    if (!is_array($columns)) throw new CbUserVisibleException('Neplatné sloupcové filtry.');
    $out['sloupce'] = [];
    foreach (['cas','autor','agenda','udalost','cil','vysledek'] as $key) {
        $value = $columns[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > 150) throw new CbUserVisibleException('Neplatný sloupcový filtr.');
        $out['sloupce'][$key] = trim($value);
    }
    return $out;
}

/** Odkaz neobsahuje technické interní hodnoty modelu filtru. */
function cb_historie_url(array $filters, array $extra = []): string
{
    $params = ['m'=>'administrace','page'=>'historie_cinnosti'];
    foreach (['od','do','autor','osoba','pobocka','modul','zdroj','pohled','datum','strana','pocet','druh'] as $key) $params['hc_'.$key] = $filters[$key];
    $params['hc_f'] = $filters['sloupce'];
    $extra = array_replace($extra, isset($extra['hc_strana']) ? ['hc_p'=>$extra['hc_strana']] : []);
    $params['hc_p'] = $filters['strana']; $params['hc_per'] = $filters['pocet'];
    unset($params['hc_strana'], $params['hc_pocet']);
    unset($extra['hc_strana']);
    return cb_root_url('index.php?'.http_build_query(array_replace($params, $extra), '', '&', PHP_QUERY_RFC3986));
}
