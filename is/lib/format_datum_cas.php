<?php
declare(strict_types=1);

if (!function_exists('cb_dt_now_prague')) {
    function cb_dt_now_prague(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_dt_now_utc_z')) {
    function cb_dt_now_utc_z(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }
}

if (!function_exists('cb_dt_today_prague')) {
    function cb_dt_today_prague(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    }
}

if (!function_exists('cb_dt_normalize_ymd')) {
    function cb_dt_normalize_ymd(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        throw new RuntimeException('Neplatne datum Y-m-d: ' . $raw);
    }
}

if (!function_exists('cb_dt_next_date')) {
    function cb_dt_next_date(string $date): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro posun.');
        }

        return $dt->modify('+1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_dt_format_date_cs')) {
    function cb_dt_format_date_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro formatovani: ' . $date);
        }
        return $dt->format('d.m.Y');
    }
}

if (!function_exists('cb_dt_format_date_short_cs')) {
    function cb_dt_format_date_short_cs(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            $dt = new DateTimeImmutable($value);
            if (preg_match('/\d{1,2}:\d{2}/', $value) === 1) {
                return $dt->format('j.n.Y G:i');
            }
            return $dt->format('j.n.Y');
        } catch (Throwable $e) {
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!($dt instanceof DateTimeImmutable)) {
            return $value;
        }
        return $dt->format('j.n.Y');
    }
}

if (!function_exists('cb_dt_format_date_input_cs')) {
    function cb_dt_format_date_input_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro vstup: ' . $date);
        }
        return $dt->format('j.n.Y');
    }
}

if (!function_exists('cb_dt_format_datetime_cs')) {
    function cb_dt_format_datetime_cs(string $datetime): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $datetime;
        }
        return $dt->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_dt_format_datetime_short_cs')) {
    function cb_dt_format_datetime_short_cs(string $datetime): string
    {
        try {
            $dt = new DateTimeImmutable($datetime);
        } catch (Throwable $e) {
            return $datetime;
        }
        return $dt->format('j.n.Y G:i');
    }
}

if (!function_exists('cb_dt_format_month_year_cs')) {
    function cb_dt_format_month_year_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro mesic: ' . $date);
        }
        static $months = [
            1 => 'leden',
            2 => 'únor',
            3 => 'březen',
            4 => 'duben',
            5 => 'květen',
            6 => 'červen',
            7 => 'červenec',
            8 => 'srpen',
            9 => 'září',
            10 => 'říjen',
            11 => 'listopad',
            12 => 'prosinec',
        ];
        $month = (int)$dt->format('n');
        return (($months[$month] ?? '') . ' ' . $dt->format('Y'));
    }
}

if (!function_exists('cb_dt_time_hm')) {
    function cb_dt_time_hm(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }
        if (preg_match('/^(\d{2}):(\d{2})(:\d{2})?$/', $time, $m) === 1) {
            return (string)(int)$m[1] . ':' . $m[2];
        }
        return $time;
    }
}

if (!function_exists('cb_dt_weekday_name_cs')) {
    function cb_dt_weekday_name_cs(DateTimeImmutable $dt): string
    {
        static $weekdays = [
            'Monday' => 'Pondělí',
            'Tuesday' => 'Úterý',
            'Wednesday' => 'Středa',
            'Thursday' => 'Čtvrtek',
            'Friday' => 'Pátek',
            'Saturday' => 'Sobota',
            'Sunday' => 'Neděle',
        ];

        return $weekdays[$dt->format('l')] ?? '';
    }
}

if (!function_exists('cb_dt_weekday_date_label_cs')) {
    function cb_dt_weekday_date_label_cs(DateTimeImmutable $dt, bool $lowercase = true): string
    {
        $weekday = cb_dt_weekday_name_cs($dt);
        if ($lowercase) {
            $weekday = mb_strtolower($weekday);
        }
        return trim($weekday . ' ' . $dt->format('j.n.Y'));
    }
}

if (!function_exists('cb_dt_workday_start')) {
    function cb_dt_workday_start(DateTimeImmutable|string|null $base = null, int $startHour = 6): DateTimeImmutable
    {
        $tz = new DateTimeZone('Europe/Prague');

        if ($base instanceof DateTimeImmutable) {
            $now = $base->setTimezone($tz);
        } elseif (is_string($base) && trim($base) !== '') {
            $now = new DateTimeImmutable($base, $tz);
        } else {
            $now = new DateTimeImmutable('now', $tz);
        }

        $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . sprintf('%02d', $startHour) . ':00:00', $tz);
        if (!($todayStart instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se urcit zacatek pracovniho dne.');
        }

        return ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;
    }
}

if (!function_exists('cb_dt_workday_date')) {
    function cb_dt_workday_date(DateTimeImmutable|string|null $base = null, int $startHour = 6): string
    {
        return cb_dt_workday_start($base, $startHour)->format('Y-m-d');
    }
}

if (!function_exists('cb_dt_workday_range_utc')) {
    function cb_dt_workday_range_utc(string $date, int $startHour = 6): array
    {
        $date = cb_dt_normalize_ymd($date);
        $tz = new DateTimeZone('Europe/Prague');
        $startText = sprintf('%02d', $startHour) . ':00:00';
        $fromLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $startText, $tz);
        $toLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', cb_dt_next_date($date) . ' ' . $startText, $tz);

        if (!($fromLocal instanceof DateTimeImmutable) || !($toLocal instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatny den pro interval.');
        }

        return [
            'from_z' => $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'from_db' => $fromLocal->format('Y-m-d H:i:s'),
            'to_db' => $toLocal->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('cb_dt_format_range_cs')) {
    function cb_dt_format_range_cs(string $fromDate, string $toDate, int $startHour = 6): string
    {
        $fromDate = cb_dt_normalize_ymd($fromDate);
        $toDate = cb_dt_normalize_ymd($toDate);
        if ($fromDate === '' || $toDate === '' || $fromDate > $toDate) {
            return '';
        }

        $tz = new DateTimeZone('Europe/Prague');
        $startText = sprintf('%02d', $startHour) . ':00:00';
        $fromDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fromDate . ' ' . $startText, $tz);
        $toDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', cb_dt_next_date($toDate) . ' ' . $startText, $tz);
        if (!($fromDt instanceof DateTimeImmutable) || !($toDt instanceof DateTimeImmutable)) {
            return '';
        }

        return $fromDt->format('d.m.Y H:i:s') . ' - ' . $toDt->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_dt_report_date')) {
    function cb_dt_report_date(?string $localDateTime, int $startHour = 6): string
    {
        if (!is_string($localDateTime) || trim($localDateTime) === '') {
            return cb_dt_today_prague();
        }

        try {
            $dt = new DateTimeImmutable($localDateTime, new DateTimeZone('Europe/Prague'));
            $hour = (int)$dt->format('G');
            if ($hour < $startHour) {
                $dt = $dt->modify('-1 day');
            }
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            return cb_dt_today_prague();
        }
    }
}

if (!function_exists('cb_dt_utc_to_local_nullable')) {
    function cb_dt_utc_to_local_nullable(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $dt = new DateTimeImmutable(trim($value), new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}
