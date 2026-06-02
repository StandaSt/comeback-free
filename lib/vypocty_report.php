<?php
// lib/vypocty_report.php * K10 výpočty denního reportu
declare(strict_types=1);

/*
 * Účel:
 * - společné výpočty pro zadání denního reportu
 * - bez zápisu do DB
 */

function cb_report_parse_branch_end_time(?string $value): array
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return ['hour' => 0, 'minute' => 0];
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m) === 1) {
        return [
            'hour' => max(0, min(23, (int)$m[1])),
            'minute' => max(0, min(59, (int)$m[2])),
        ];
    }
    if (preg_match('/^\d{1,2}$/', $raw) === 1) {
        return [
            'hour' => max(0, min(23, (int)$raw)),
            'minute' => 0,
        ];
    }

    return ['hour' => 0, 'minute' => 0];
}

function cb_report_save_at_ts(DateTimeImmutable $reportDateDt, ?string $branchEndTime, int $reportSaveMinutes, int $workdayStartHour = 6): int
{
    $endTime = cb_report_parse_branch_end_time($branchEndTime);
    $endTimeMinutes = ((int)$endTime['hour'] * 60) + (int)$endTime['minute'];
    $workdayStartMinutes = max(0, min(23, $workdayStartHour)) * 60;
    $endBaseDate = $endTimeMinutes < $workdayStartMinutes ? $reportDateDt->modify('+1 day') : $reportDateDt;

    return $endBaseDate
        ->setTime((int)$endTime['hour'], (int)$endTime['minute'], 0)
        ->modify('-' . max(0, $reportSaveMinutes) . ' minutes')
        ->getTimestamp();
}

function cb_report_refresh_at_ts(int $reportSaveAtTs, int $offsetSeconds = 300): int
{
    return $reportSaveAtTs > $offsetSeconds ? $reportSaveAtTs - $offsetSeconds : 0;
}

function cb_report_make_time_label(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return '0 min 00 s';
    }

    $minutes = intdiv($seconds, 60);
    $restSeconds = $seconds % 60;

    return $minutes . ' min ' . sprintf('%02d', $restSeconds) . ' s';
}

function cb_report_money_value(?array $row, string $key): float
{
    if (!is_array($row) || !array_key_exists($key, $row) || $row[$key] === null) {
        return 0.0;
    }

    return (float)$row[$key];
}

function cb_report_has_required_cash_values(?array $draft): bool
{
    return is_array($draft)
        && array_key_exists('hotovost', $draft)
        && $draft['hotovost'] !== null
        && array_key_exists('terminal', $draft)
        && $draft['terminal'] !== null
        && array_key_exists('stravenky', $draft)
        && $draft['stravenky'] !== null;
}

function cb_report_vypocet_rozdil(array $restiaSummary, ?array $draft): ?float
{
    if (!cb_report_has_required_cash_values($draft)) {
        return null;
    }

    $reportIncome = (float)($restiaSummary['wolt'] ?? 0)
        + (float)($restiaSummary['bolt'] ?? 0)
        + (float)($restiaSummary['dj'] ?? 0)
        + (float)($restiaSummary['web'] ?? 0)
        + (float)($restiaSummary['wolt_cash'] ?? 0)
        + (float)($restiaSummary['dj_cash'] ?? 0)
        + cb_report_money_value($draft, 'terminal')
        + cb_report_money_value($draft, 'stravenky')
        + cb_report_money_value($draft, 'hotovost');
    $reportExpenses = cb_report_money_value($draft, 'vydaje_benzin')
        + cb_report_money_value($draft, 'vydaje_auta')
        + cb_report_money_value($draft, 'vydaje_suroviny')
        + cb_report_money_value($draft, 'vydaje_ostatni')
        + cb_report_money_value($draft, 'vydaje_phm_soukrome');

    return $reportIncome + $reportExpenses - (float)($restiaSummary['trzba'] ?? 0);
}

// lib/vypocty_report.php * Konec souboru
