<?php
// lib/cteni_dat.php * Verze: V5 * Aktualizace: 8.2.2026 * Počet řádků: 207
declare(strict_types=1);

/*
 * ČTENÍ DAT ZE SMĚN (GraphQL) – automat po loginu
 *
 * V5 (8.2.2026):
 * - Skript je určený k volání z login_smeny.php přes require (bez výstupu, bez redirectu).
 * - Zapíše do pomocne/data_smeny.txt jen 3 bloky dat:
 *   1) USER:PROFILE (userGetLogged – základní profil)
 *   2) USER:BRANCHES (actionHistoryFindById -> user -> workingBranchNames + mainBranchName)
 *   3) ROLETYPE:LIST (shiftRoleTypeFindAll – id, name)
 *
 * Bezpečnost:
 * - heslo se sem NIKDY neposílá a NIKDE neukládá
 * - access token se NIKDY nezapisuje do txt ani do logu
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$OUT_FILE = __DIR__ . '/data_smeny.txt';
$GQL_URL  = 'https://smeny.pizzacomeback.cz/graphql';

function cb_txt_line(string $label, mixed $data): string
{
    $val = '';
    if (is_bool($data)) {
        $val = $data ? 'true' : 'false';
    } elseif ($data === null) {
        $val = 'null';
    } elseif (is_int($data) || is_float($data)) {
        $val = (string)$data;
    } elseif (is_string($data)) {
        $val = $data;
    } else {
        $tmp = json_encode($data, JSON_UNESCAPED_UNICODE);
        $val = ($tmp === false) ? '[json_encode_failed]' : $tmp;
    }

    $val = str_replace(["\r", "\n"], [' ', ' '], $val);
    $ts  = date('Y-m-d H:i:s');

    return $ts . ' | ' . $label . ' | ' . $val . PHP_EOL;
}

function cb_file_append(string $file, string $line): void
{
    @mkdir(dirname($file), 0775, true);
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function cb_get_token_or_log(string $outFile): ?string
{
    $token = $_SESSION['cb_token'] ?? '';
    if (!is_string($token) || $token === '') {
        cb_file_append($outFile, cb_txt_line('SMENY:ERROR', 'Chybí token v session (cb_token).'));
        return null;
    }
    return $token;
}

function cb_get_ctx(): array
{
    $cbUser = $_SESSION['cb_user'] ?? null;

    $email = '---';
    $idUser = 0;

    if (is_array($cbUser) && isset($cbUser['email'])) {
        $email = (string)$cbUser['email'];
    }
    if (is_array($cbUser) && isset($cbUser['id_user'])) {
        $idUser = (int)$cbUser['id_user'];
    }

    return ['email' => $email, 'id_user' => $idUser];
}

function gql_call(string $url, string $query, array $vars, string $token): array
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $token];

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode(['query' => $query, 'variables' => $vars], JSON_UNESCAPED_UNICODE),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $out = curl_exec($ch);
    if ($out === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL chyba: ' . $err);
    }
    curl_close($ch);

    $json = json_decode($out, true);
    if (!is_array($json)) {
        throw new RuntimeException('Neplatná odpověď z API.');
    }
    if (!empty($json['errors'])) {
        $m = $json['errors'][0]['message'] ?? 'Neznámá chyba.';
        if (is_array($m)) {
            $m = json_encode($m, JSON_UNESCAPED_UNICODE);
        }
        throw new RuntimeException((string)$m);
    }

    $data = $json['data'] ?? [];
    return is_array($data) ? $data : [];
}

function cb_write_profile(string $outFile, string $url, string $token): void
{
    try {
        $me = gql_call(
            $url,
            'query{ userGetLogged{ id name surname email phoneNumber active approved createTime lastLoginTime } }',
            [],
            $token
        );
        cb_file_append($outFile, cb_txt_line('USER:PROFILE', $me['userGetLogged'] ?? null));
    } catch (Throwable $e) {
        cb_file_append($outFile, cb_txt_line('USER:PROFILE:ERROR', $e->getMessage()));
    }
}

function cb_write_branches(string $outFile, string $url, string $token, int $idUser): void
{
    try {
        $data = gql_call(
            $url,
            'query($id:Int!){ actionHistoryFindById(id:$id){ user{ workingBranchNames mainBranchName } } }',
            ['id' => $idUser],
            $token
        );

        $u = $data['actionHistoryFindById']['user'] ?? null;
        if (!is_array($u)) {
            cb_file_append($outFile, cb_txt_line('USER:BRANCHES:ERROR', 'Chybí user v odpovědi (actionHistoryFindById).'));
            return;
        }

        cb_file_append($outFile, cb_txt_line('USER:BRANCHES', [
            'workingBranchNames' => $u['workingBranchNames'] ?? [],
            'mainBranchName' => $u['mainBranchName'] ?? null,
        ]));
    } catch (Throwable $e) {
        cb_file_append($outFile, cb_txt_line('USER:BRANCHES:ERROR', $e->getMessage()));
    }
}

function cb_write_role_types(string $outFile, string $url, string $token): void
{
    try {
        $data = gql_call(
            $url,
            'query{ shiftRoleTypeFindAll{ id name } }',
            [],
            $token
        );
        cb_file_append($outFile, cb_txt_line('ROLETYPE:LIST', $data['shiftRoleTypeFindAll'] ?? null));
    } catch (Throwable $e) {
        cb_file_append($outFile, cb_txt_line('ROLETYPE:LIST:ERROR', $e->getMessage()));
    }
}

try {
    $ctx = cb_get_ctx();

    cb_file_append($OUT_FILE, cb_txt_line('RUN:START', [
        'mode' => 'login_auto',
        'email' => $ctx['email'],
        'id_user' => $ctx['id_user'],
    ]));

    $token = cb_get_token_or_log($OUT_FILE);
    if ($token === null) {
        cb_file_append($OUT_FILE, cb_txt_line('RUN:END', ['mode' => 'login_auto', 'error' => 'missing_token']));
        return;
    }

    cb_write_profile($OUT_FILE, $GQL_URL, $token);

    if ((int)$ctx['id_user'] > 0) {
        cb_write_branches($OUT_FILE, $GQL_URL, $token, (int)$ctx['id_user']);
    } else {
        cb_file_append($OUT_FILE, cb_txt_line('USER:BRANCHES:ERROR', 'Chybí id_user v session (cb_user).'));
    }

    cb_write_role_types($OUT_FILE, $GQL_URL, $token);

    cb_file_append($OUT_FILE, cb_txt_line('RUN:END', ['mode' => 'login_auto']));

} catch (Throwable $e) {
    cb_file_append($OUT_FILE, cb_txt_line('SMENY:FATAL', $e->getMessage()));
    cb_file_append($OUT_FILE, cb_txt_line('RUN:END', ['mode' => 'login_auto', 'error' => 'fatal']));
}

// lib/cteni_dat.php * Verze: V5 * Aktualizace: 8.2.2026
// konec souboru