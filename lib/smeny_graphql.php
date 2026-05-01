<?php
// lib/smeny_graphql.php * Verze: V3 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * Pomocník pro volání Směny (GraphQL)
 *
 * V3:
 * - samotné volání cb_smeny_graphql nic nezapisuje do DB
 * - jen sbírá metriky do session bufferu (lib/api_smeny_log.php)
 */

require_once __DIR__ . '/api_smeny_log.php';

/**
 * @return array<string, mixed>  data část odpovědi GraphQL
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
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_POSTFIELDS      => $payloadJson,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_TIMEOUT         => $timeout,
    ]);

    $out = '';
    $ok = true;
    $chyba = null;

    try {
        $out = curl_exec($ch);
        if ($out === false) {
            $ok = false;
            $err = curl_error($ch);
            $chyba = 'cURL chyba';
            throw new RuntimeException('cURL chyba: ' . $err);
        }

        $json = json_decode($out, true);
        if (!is_array($json)) {
            $ok = false;
            $chyba = 'Neplatná odpověď z API';
            throw new RuntimeException('Neplatná odpověď z API.');
        }

        if (!empty($json['errors'])) {
            $ok = false;

            // pro DB chceme krátký text, ne JSON román
            $chyba = 'neplatné přihlášení';

            // ale výjimku necháme původní, ať to UI/diagnostika vidí
            $m = $json['errors'][0]['message'] ?? 'Neznámá chyba.';
            if (is_array($m)) {
                $m = json_encode($m, JSON_UNESCAPED_UNICODE);
            }
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

function cb_login_finalize_after_ok(string $token, int $timeoutMin = 20): void
{
    if ($token === '') {
        throw new RuntimeException('Chybí token pro dokončení přihlášení.');
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
        throw new RuntimeException('Nepodařilo se dokončit načtení profilu uživatele.');
    }

    $idUser = (int)$u['id'];

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
        throw new RuntimeException('Nepodařilo se dokončit načtení poboček uživatele.');
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
        'id_user'   => $idUser,
        'name'      => (string)($u['name'] ?? ''),
        'surname'   => (string)($u['surname'] ?? ''),
        'email'     => (string)($u['email'] ?? ''),
        'telefon'   => (string)($u['phoneNumber'] ?? ''),
        'active'    => (bool)($u['active'] ?? false),
        'approved'  => (bool)($u['approved'] ?? false),
        'roles'     => $roles,
        'sloty'     => $sloty,
    ];

    $_SESSION['cb_user_profile'] = $u;
    $_SESSION['cb_user_branches'] = [
        'workingBranchNames' => $working,
        'mainBranchName' => $mainBranchName,
    ];

    require_once __DIR__ . '/zapis_dat_txt.php';

    require_once __DIR__ . '/../db/db_user_login.php';
    cb_db_user_login();

    require_once __DIR__ . '/restia_access_exist.php';

    if ($timeoutMin <= 0) {
        $timeoutMin = 20;
    }

    $_SESSION['cb_timeout_min'] = $timeoutMin;
    $_SESSION['cb_session_start_ts'] = time();
    $_SESSION['cb_last_activity_ts'] = time();
}

// lib/smeny_graphql.php * Verze: V3 * Aktualizace: 21.2.2026
// Počet řádků: 98
// Konec souboru
