<?php
declare(strict_types=1);

/*
 * Ucel souboru: Nacist lokalni systemove a uzivatelske nastaveni do session
 * pri dokonceni prihlaseni. Funkce nema zadnou vazbu na API Smeny.
 */

function cb_login_load_settings_to_session(int $idUser): void
{
    $conn = db();

    $resSystem = $conn->query('SELECT restia_online, on_2fa, system_logout, pauza_obdobi, log_akce, log_1, log_2, log_3, log_4, notif_chyby, notif_bad_login FROM set_system WHERE id_set = 1 LIMIT 1');
    if (!($resSystem instanceof mysqli_result)) {
        throw new RuntimeException('Nepodarilo se nacist set_system.');
    }

    $rowSystem = $resSystem->fetch_assoc();
    $resSystem->free();
    if (!is_array($rowSystem)) {
        throw new RuntimeException('Chybi set_system.');
    }
    cb_store_system_settings($rowSystem);

    $stmtUserSet = $conn->prepare(
        'SELECT prodleva, pismo, dark, obdobi_od, obdobi_do, obdobi_mode, aktivni_modul FROM user_set WHERE id_user = ? LIMIT 1'
    );
    if (!($stmtUserSet instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodarilo se nacist user_set.');
    }

    $stmtUserSet->bind_param('i', $idUser);
    $stmtUserSet->execute();
    $resUserSet = $stmtUserSet->get_result();
    $rowUserSet = ($resUserSet instanceof mysqli_result) ? $resUserSet->fetch_assoc() : null;
    if ($resUserSet instanceof mysqli_result) {
        $resUserSet->free();
    }
    $stmtUserSet->close();

    if (!is_array($rowUserSet)) {
        throw new RuntimeException('Chybi user_set.');
    }

    cb_store_user_settings($rowUserSet);

    $normalizePeriodDateTime = static function (string $v): string {
        $v = trim(str_replace('T', ' ', $v));
        if ($v === '') {
            return '';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $v, $m) === 1) {
            $v = $m[1] . '-' . $m[2] . '-' . $m[3] . ' 06:00:00';
        } elseif (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$~', $v, $m) === 1) {
            $v .= ':00';
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$~', $v, $m) !== 1) {
            return '';
        }

        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $h = (int)$m[4];
        $mi = (int)$m[5];
        $s = (int)$m[6];
        if (!checkdate($mo, $d, $y) || $h > 23 || $mi > 59 || $s > 59) {
            return '';
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $s);
    };

    $nowPeriod = new DateTimeImmutable('now');
    $currentWorkdayDate = $nowPeriod;
    if ((int)$nowPeriod->format('G') < 6) {
        $currentWorkdayDate = $currentWorkdayDate->modify('-1 day');
    }
    $defaultOd = $currentWorkdayDate->modify('-1 day')->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $defaultDo = $currentWorkdayDate->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $maxDo = $nowPeriod->format('Y-m-d H:i:s');

    $periodOd = $normalizePeriodDateTime((string)($rowUserSet['obdobi_od'] ?? ''));
    $periodDo = $normalizePeriodDateTime((string)($rowUserSet['obdobi_do'] ?? ''));
    $periodMode = trim((string)($rowUserSet['obdobi_mode'] ?? 'manual'));
    if (!in_array($periodMode, ['dnes', 'vcera', 'tyden', 'mesic', 'rok', 'vse', 'manual'], true)) {
        $periodMode = 'manual';
    }

    if ($periodOd === '' || $periodDo === '' || $periodOd > $periodDo || $periodOd > $maxDo || $periodDo > $maxDo) {
        $periodOd = $defaultOd;
        $periodDo = $defaultDo;
        $periodMode = 'vcera';
    }

    $_SESSION['cb_obdobi_od'] = $periodOd;
    $_SESSION['cb_obdobi_do'] = $periodDo;
    $_SESSION['cb_obdobi_mode'] = $periodMode;
    cb_store_user_settings([
        'obdobi_od' => $periodOd,
        'obdobi_do' => $periodDo,
        'obdobi_mode' => $periodMode,
    ]);
}
