<?php
/*
 * Ucel souboru: Pripravi a overi globalni vyber obdobi aplikace.
 * Soubor nema HTML vystup; stav uklada do session a vraci data pro zobrazeni.
 */
declare(strict_types=1);

if (!function_exists('cb_obdobi_normalize_datetime')) {
    /**
     * Normalizuje datum a cas na format Y-m-d H:i:s, jinak vrati prazdny retezec.
     */
    function cb_obdobi_normalize_datetime(string $value): string
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return '';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $value, $match) === 1) {
            $value = $match[1] . '-' . $match[2] . '-' . $match[3] . ' 06:00:00';
        } elseif (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$~', $value) === 1) {
            $value .= ':00';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$~', $value, $match) !== 1) {
            return '';
        }
        $year = (int)$match[1];
        $month = (int)$match[2];
        $day = (int)$match[3];
        $hour = (int)$match[4];
        $minute = (int)$match[5];
        $second = (int)$match[6];
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return '';
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}

if (!function_exists('cb_obdobi_priprav_globalni_vyber')) {
    /**
     * Pripravi platny globalni vyber obdobi ze session a aktualnich dat.
     *
     * @return array{od:string,do:string,mode:string,prodleva_ms:int,max:string}
     */
    function cb_obdobi_priprav_globalni_vyber(): array
    {
        $now = new DateTimeImmutable('now');
        $currentWorkday = (int)$now->format('G') < 6 ? $now->modify('-1 day') : $now;
        $defaultOd = $currentWorkday->modify('-1 day')->setTime(6, 0, 0)->format('Y-m-d H:i:s');
        $defaultDo = $currentWorkday->setTime(6, 0, 0)->format('Y-m-d H:i:s');
        $max = $now->format('Y-m-d H:i:s');

        $maxResult = db()->query('SELECT MAX(konec) AS posledni_konec FROM online_restia WHERE konec IS NOT NULL');
        if ($maxResult instanceof mysqli_result) {
            $maxRow = $maxResult->fetch_assoc();
            $maxResult->free();
            $lastEnd = trim((string)($maxRow['posledni_konec'] ?? ''));
            if ($lastEnd !== '') {
                $max = $lastEnd;
            }
        }

        $allowedModes = ['dnes', 'vcera', 'tyden', 'mesic', 'rok', 'vse', 'manual'];
        $mode = trim((string)($_SESSION['cb_obdobi_mode'] ?? 'manual'));
        if (!in_array($mode, $allowedModes, true)) {
            $mode = 'manual';
        }

        $od = $defaultOd;
        $do = $defaultDo;
        $sessionOd = cb_obdobi_normalize_datetime((string)($_SESSION['cb_obdobi_od'] ?? ''));
        $sessionDo = cb_obdobi_normalize_datetime((string)($_SESSION['cb_obdobi_do'] ?? ''));
        if ($sessionOd !== '' && $sessionDo !== '' && $sessionOd <= $max && $sessionOd <= $sessionDo && $sessionDo <= $max) {
            $od = $sessionOd;
            $do = $sessionDo;
        }

        $prodlevaMs = (int)cb_system_setting('pauza_obdobi', 1000);
        if (!in_array($prodlevaMs, range(1000, 10000, 1000), true)) {
            $prodlevaMs = 1000;
        }
        $userProdleva = (int)cb_user_setting('prodleva', $prodlevaMs);
        if (in_array($userProdleva, range(1000, 10000, 1000), true)) {
            $prodlevaMs = $userProdleva;
        }

        if (in_array($mode, ['dnes', 'tyden', 'mesic', 'rok', 'vse'], true)) {
            $do = $max;
        }

        $_SESSION['cb_obdobi_od'] = $od;
        $_SESSION['cb_obdobi_do'] = $do;
        $_SESSION['cb_obdobi_mode'] = $mode;

        return ['od' => $od, 'do' => $do, 'mode' => $mode, 'prodleva_ms' => $prodlevaMs, 'max' => $max];
    }
}
