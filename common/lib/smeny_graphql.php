<?php
// lib/smeny_graphql.php * Verze: V4 * Aktualizace: 13.05.2026
declare(strict_types=1);

/*
 * Pomocnik pro volani Smeny (GraphQL)
 *
 * V4:
 * - samotne volani cb_smeny_graphql nic nezapisuje do DB
 * - jen sbira metriky do session bufferu (lib/api_smeny_log.php)
 * - po loginu centralne nacte set_system a user_set do session
 */

require_once __DIR__ . '/api_smeny_log.php';

/**
 * @return array<string, mixed>
 */
function cb_smeny_graphql(string $url, string $query, array $vars = [], ?string $token = null, ?int $timeoutSec = null): array
{
    $startTs = microtime(true);

    $payloadJson = (string)json_encode(
        ['query' => $query, 'variables' => $vars],
        JSON_UNESCAPED_UNICODE
    );

    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $timeout = (int)($timeoutSec ?? 20);
    if ($timeout <= 0) {
        $timeout = 20;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $out = '';
    $ok = true;
    $chyba = null;
    $httpCode = 0;
    $curlError = '';

    try {
        $out = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($out === false) {
            $ok = false;
            $curlError = curl_error($ch);
            $chyba = 'cURL chyba';
            throw new RuntimeException('cURL chyba: ' . $curlError);
        }

        $json = json_decode($out, true);
        if (!is_array($json)) {
            $ok = false;
            $chyba = 'Neplatna odpoved z API';
            throw new RuntimeException('Neplatna odpoved z API.');
        }

        if (!empty($json['errors'])) {
            $ok = false;

            $m = $json['errors'][0]['message'] ?? 'Neznama chyba.';
            if (is_array($m)) {
                $m = json_encode($m, JSON_UNESCAPED_UNICODE);
            }
            $chyba = (string)$m;
            throw new RuntimeException((string)$m);
        }

        $data = $json['data'] ?? [];
        return is_array($data) ? $data : [];
    } finally {
        $row = smeny_api_make_row($startTs, $payloadJson, (string)$out, $ok, $chyba);
        smeny_api_buffer_add($row);

        curl_close($ch);
    }
}

function cb_login_load_settings_to_session(int $idUser): void
{
    $conn = db();

    $resSystem = $conn->query('SELECT restia_online, on_2fa, system_logout, pauza_obdobi, zamek, log_akce, log_1, log_2, log_3, log_4, notif_chyby, notif_bad_login FROM set_system WHERE id_set = 1 LIMIT 1');
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
        'SELECT prodleva, pismo, dark, logout_limit, kpi, obdobi_od, obdobi_do, obdobi_mode FROM user_set WHERE id_user = ? LIMIT 1'
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
    if ($periodMode === 'dnes') {
        $periodMode = 'vcera';
    }
    if (!in_array($periodMode, ['vcera', 'tyden', 'mesic', 'rok', 'manual'], true)) {
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

function cb_login_finalize_after_ok(string $token): void
{
    if ($token === '') {
        throw new RuntimeException('Chybi token pro dokonceni prihlaseni.');
    }

    $gqlUrl = 'https://smeny.pizzacomeback.cz/graphql';

    $me = cb_smeny_graphql(
        $gqlUrl,
        'query{
            userGetLogged{
                id
                name
                surname
                email
                phoneNumber
                active
                approved
                createTime
                lastLoginTime
                roles{ id name }
                shiftRoleTypeNames
            }
        }',
        [],
        $token
    );

    $u = $me['userGetLogged'] ?? null;
    if (!is_array($u) || empty($u['id']) || empty($u['email'])) {
        throw new RuntimeException('Nepodarilo se dokoncit nacteni profilu uzivatele.');
    }

    $idUser = (int)$u['id'];

    cb_session_regenerate_after_login();

    $roles = $u['roles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }

    $sloty = $u['shiftRoleTypeNames'] ?? [];
    if (!is_array($sloty)) {
        $sloty = [];
    }

    $br = cb_smeny_graphql(
        $gqlUrl,
        'query{
            userGetLogged{
                workingBranchNames
                mainBranchName
            }
        }',
        [],
        $token
    );

    $brUser = $br['userGetLogged'] ?? null;
    if (!is_array($brUser)) {
        throw new RuntimeException('Nepodarilo se dokoncit nacteni pobocek uzivatele.');
    }

    $working = $brUser['workingBranchNames'] ?? [];
    if (!is_array($working)) {
        $working = [];
    }

    $mainBranchName = trim((string)($brUser['mainBranchName'] ?? ''));
    if ($mainBranchName === '') {
        $mainBranchName = null;
    }

    $_SESSION['cb_user'] = [
        'id_user' => $idUser,
        'name' => (string)($u['name'] ?? ''),
        'surname' => (string)($u['surname'] ?? ''),
        'email' => (string)($u['email'] ?? ''),
        'telefon' => (string)($u['phoneNumber'] ?? ''),
        'active' => (bool)($u['active'] ?? false),
        'approved' => (bool)($u['approved'] ?? false),
        'roles' => $roles,
        'sloty' => $sloty,
    ];

    $_SESSION['cb_user_profile'] = $u;
    $_SESSION['cb_user_branches'] = [
        'workingBranchNames' => $working,
        'mainBranchName' => $mainBranchName,
    ];
    cb_session_bind_after_login();

    require_once __DIR__ . '/../db/db_user_login.php';
    cb_db_user_login();
    cb_login_load_settings_to_session($idUser);

    require_once __DIR__ . '/restia_access_exist.php';

    $userLogoutLimit = cb_user_setting('logout_limit', null);
    $_SESSION['cb_timeout_min'] = $userLogoutLimit !== null
        ? (int)$userLogoutLimit
        : (int)cb_system_setting('system_logout', 20);
    $_SESSION['cb_session_start_ts'] = time();
    $_SESSION['cb_last_activity_ts'] = time();
}
