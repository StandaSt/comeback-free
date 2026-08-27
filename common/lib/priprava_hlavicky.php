<?php
/*
 * Ucel souboru: Pripravi data pro viditelnou hlavicku aplikace.
 * Soubor nema HTML vystup; sestavuje stav uzivatele, sluzeb, obdobi a navigace.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db/db_user_role.php';
require_once __DIR__ . '/obdobi_vyber.php';

if (!function_exists('cb_head_restia_token_is_valid')) {
    /**
     * Overi, zda ma ulozeny token Restia dostatecnou platnost.
     */
    function cb_head_restia_token_is_valid(mysqli $conn): bool
    {
        $stmt = $conn->prepare('SELECT expires_at FROM restia_token WHERE id_restia_token = 1 LIMIT 1');
        if ($stmt === false) {
            return false;
        }
        $stmt->execute();
        $stmt->bind_result($expiresAt);
        $valid = false;
        if ($stmt->fetch()) {
            try {
                $expires = new DateTimeImmutable(trim((string)$expiresAt), new DateTimeZone('UTC'));
                $valid = $expires > (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+60 seconds');
            } catch (Throwable $e) {
                $valid = false;
            }
        }
        $stmt->close();
        return $valid;
    }
}

if (!function_exists('cb_head_restia_online_is_running')) {
    /**
     * Overi, zda prave probiha online zpracovani Restia.
     */
    function cb_head_restia_online_is_running(mysqli $conn): bool
    {
        $result = $conn->query('SELECT id_akce FROM online_restia WHERE aktivni = 1 LIMIT 1');
        if (!($result instanceof mysqli_result)) {
            return false;
        }
        $running = $result->num_rows > 0;
        $result->free();
        return $running;
    }
}

// Pripravi identitu prihlaseneho uzivatele pro hlavicku.
$cbLoginOk = !empty($_SESSION['login_ok']);
$cbUser = $_SESSION['cb_user'] ?? [];
$cbUserName = 'Uzivatel';
$cbUserRole = '-';
$cbUserRoleLabel = '-';
$cbUserRoleId = 0;
if (is_array($cbUser)) {
    $fullName = trim((string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? ''));
    $cbUserName = $fullName !== '' ? $fullName : (string)($cbUser['jmeno'] ?? $cbUser['email'] ?? $cbUser['login'] ?? $cbUserName);
    $cbUserRole = (string)($cbUser['role'] ?? $cbUser['nazev_role'] ?? $cbUserRole);
    $cbUserRoleLabel = $cbUserRole;
    $cbUserRoleId = (int)($cbUser['id_role'] ?? 0);
}
if ($cbUserRole !== '-' && $cbUserRoleId > 0) {
    $cbUserRole .= ' (' . $cbUserRoleId . ')';
}

// Pripravi stavove semafory sluzeb zobrazenych v hlavicce.
$sysDb = 'ok';
$sysSmeny = 'ok';
$sysRestia = 'bad';
try {
    $connRestia = db();
    if (!cb_head_restia_online_is_running($connRestia) && !cb_head_restia_token_is_valid($connRestia)) {
        require_once __DIR__ . '/restia_ziskej_access.php';
    }
    if (!cb_head_restia_online_is_running($connRestia)) {
        $sysRestia = cb_head_restia_token_is_valid($connRestia) ? 'ok' : 'bad';
    }
} catch (Throwable $e) {
    $sysRestia = 'bad';
}

// Pripravi globalni vyber obdobi pouzivany hlavickou i dalsimi moduly.
$cbObdobi = cb_obdobi_priprav_globalni_vyber();
$cbObdobiOd = $cbObdobi['od'];
$cbObdobiDo = $cbObdobi['do'];
$cbObdobiMode = $cbObdobi['mode'];
$cbProdlevaMs = $cbObdobi['prodleva_ms'];
$cbObdobiMax = $cbObdobi['max'];

// Pripravi statistiku prihlaseni a delku aktualni session.
$cbLoginInfo = is_array($_SESSION['cb_login_info'] ?? null) ? $_SESSION['cb_login_info'] : [];
$cbCurrent = is_array($cbLoginInfo['current'] ?? null) ? $cbLoginInfo['current'] : [];
$cbPrev = is_array($cbLoginInfo['prev'] ?? null) ? $cbLoginInfo['prev'] : [];
$cbStats = is_array($cbLoginInfo['stats'] ?? null) ? $cbLoginInfo['stats'] : [];
$cbLastLoginRaw = (string)($cbPrev['kdy'] ?? $cbCurrent['kdy'] ?? '');
$cbLastLoginText = '---';
if ($cbLastLoginRaw !== '') {
    try {
        $cbLastLoginText = (new DateTimeImmutable($cbLastLoginRaw))->format('j.n.Y H:i');
    } catch (Throwable $e) {
        $cbLastLoginText = $cbLastLoginRaw;
    }
}
$cbLoginTotal = (int)($cbStats['total'] ?? 0);
$cbLoginToday = (int)($cbStats['today'] ?? 0);
$cbLoginStatsText = 'celkem ' . $cbLoginTotal . 'x / dnes ' . $cbLoginToday . 'x';
$cbStartTs = (int)($_SESSION['cb_session_start_ts'] ?? time());
$cbNowTs = time();
if ($cbStartTs <= 0 || $cbStartTs > $cbNowTs) {
    $cbStartTs = $cbNowTs;
}

// Pripravi kontext aktualniho modulu, aktualizace dat a aktualni cas.
$cbHelpdeskIsRoleOne = $cbUserRoleId === 1;
$cbHelpdeskApiUrl = cb_root_url('index.php');
try {
    $cbHeadAktualizaceDat = (new DateTimeImmutable((string)$cbObdobiMax))->format('H:i:s');
} catch (Throwable $e) {
    $cbHeadAktualizaceDat = '---';
}
$cbCurrentModule = function_exists('cb_current_module') ? cb_current_module() : 'provoz';
if (!in_array($cbCurrentModule, ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk', 'administrace'], true)) {
    $cbCurrentModule = 'provoz';
}
$cbHeaderNow = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
$cbHeaderWeekdays = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];
$cbHeaderDateText = $cbHeaderWeekdays[(int)$cbHeaderNow->format('w')] . ' ' . $cbHeaderNow->format('j.n.Y');
$cbHeaderTimeText = $cbHeaderNow->format('H:i:s');
