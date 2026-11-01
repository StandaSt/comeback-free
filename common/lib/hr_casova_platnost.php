<?php
declare(strict_types=1);
/* Casova platnost HR: ciste vyhodnoceni uzavrenych intervalu a planu zmen bez zapisu do DB.
 * Platny oznacuje pouzitelnou verzi; o jeji ucinnosti rozhoduje datum, nikoli cas ulozeni.
 */

/** Prijme pouze skutecne kalendarni datum; stejny den pouzije zapis i nocni prepocet. */
function cb_hr_den(?string $datum = null): string
{
    $datum ??= (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    $den = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
    if (!$den || $den->format('Y-m-d') !== $datum) throw new InvalidArgumentException('Neplatné datum účinnosti.');
    return $datum;
}

/** Posledni den stare verze je den pred ucinnosti nove verze. */
function cb_hr_den_pred(string $datum): string
{
    return (new DateTimeImmutable(cb_hr_den($datum)))->modify('-1 day')->format('Y-m-d');
}

/** Zrusene verze se nevraceji ani pri cteni historickeho data. */
function cb_hr_interval_plati(array $row, string $datum, string $od = 'platnost_od', string $do = 'platnost_do'): bool
{
    cb_hr_den($datum);
    return (int)($row['platny'] ?? 1) === 1 && empty($row['zruseno'])
        && (empty($row[$od]) || $row[$od] <= $datum)
        && (empty($row[$do]) || $row[$do] >= $datum);
}

/** Naplanuje nahradu jedine verze; pozdejsi samostatne naplanovane zmeny zachova.
 * Stejny pocatek znamena opravu verze, nikoli interval koncici pred svym zacatkem.
 */
function cb_hr_casovy_plan(array $rows, string $datum, string $pk, string $od = 'platnost_od', string $do = 'platnost_do'): array
{
    cb_hr_den($datum);
    $current = []; $next = null;
    foreach ($rows as $row) {
        if ((int)$row['platny'] !== 1 || !empty($row['zruseno'])) continue;
        if (cb_hr_interval_plati($row, $datum, $od, $do)) $current[] = $row;
        if (!empty($row[$od]) && $row[$od] > $datum && ($next === null || $row[$od] < $next)) $next = $row[$od];
    }
    if (count($current) > 1) throw new DomainException('Časové verze se překrývají. Nejprve opravte jejich intervaly.');
    $old = $current[0] ?? null;
    $until = $next === null ? null : cb_hr_den_pred($next);
    if ($old && !empty($old[$do]) && ($until === null || $old[$do] < $until)) $until = $old[$do];
    return ['puvodni'=>$old, 'storno'=>$old && ($old[$od] ?? null) === $datum,
        'id'=>$old ? (int)$old[$pk] : null, 'do'=>$until, 'pred'=>cb_hr_den_pred($datum)];
}

/** SQL predikat pro verze s intervalem; aliasy jsou pouze pevne interni identifikatory. */
function cb_hr_cas_sql(string $alias, string $datum = 'CURRENT_DATE()', string $od = 'platnost_od', string $do = 'platnost_do'): string
{
    foreach ([$alias,$od,$do] as $name) if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$name)) throw new InvalidArgumentException('Neplatný SQL identifikátor.');
    if ($datum !== '?' && $datum !== 'CURRENT_DATE()' && !preg_match("/^'\d{4}-\d{2}-\d{2}'$/D",$datum)) throw new InvalidArgumentException('Neplatný SQL den.');
    return "$alias.platny=1 AND ($alias.$od IS NULL OR $alias.$od<=$datum) AND ($alias.$do IS NULL OR $alias.$do>=$datum)";
}
