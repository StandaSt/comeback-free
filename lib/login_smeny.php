<?php
// lib/login_smeny.php V9 – počet řádků: 238 – aktuální čas v ČR: 7.2.2026
declare(strict_types=1);

/*
 * Přihlášení přes Směny (GraphQL API)
 * - vstup: POST email + heslo
 * - při úspěchu: uloží $_SESSION['cb_user']
 * - id_user = ID uživatele ze Směn
 * - DB zápisy / aktualizace řeší samostatný skript (NE tady)
 * - při chybě: nastaví hlášku a vrátí na úvod
 *
 * DIAGNOSTIKA
 * - loguje kroky a chyby do /log/error.log
 * - čitelné pro člověka (pevný textový formát)
 * - NEloguje heslo
 */

require_once __DIR__ . '/bootstrap.php';

function post_str(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

function cb_log_line(string $step, array $ctx = [], ?Throwable $e = null): void
{
    $dir = __DIR__ . '/../log';
    @mkdir($dir, 0775, true);
    $file = $dir . '/error.log';

    // Bezpečnost: heslo se sem nikdy nesmí dostat.
    if (isset($ctx['heslo'])) $ctx['heslo'] = '[HIDDEN]';
    if (isset($ctx['password'])) $ctx['password'] = '[HIDDEN]';

    $ts     = date('Y-m-d H:i:s');
    $uri    = (string)($_SERVER['REQUEST_URI'] ?? '');
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
    $ip     = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $sid    = (string)session_id();

    $ctxParts = [];
    foreach ($ctx as $k => $v) {
        if (is_bool($v)) {
            if ($v) {
                $v = 'true';
            } else {
                $v = 'false';
            }
        } elseif ($v === null) {
            $v = 'null';
        } elseif (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        } else {
            $v = (string)$v;
        }

        $v = str_replace(["\r", "\n"], [' ', ' '], $v);
        $ctxParts[] = $k . '=' . $v;
    }
    $ctxTxt = '';
    if ($ctxParts) {
        $ctxTxt = ' | ' . implode(' | ', $ctxParts);
    }

    $exTxt = '';
    if ($e) {
        $exTxt =
            ' | EX=' . get_class($e) .
            ' | MSG=' . str_replace(["\r", "\n"], [' ', ' '], $e->getMessage()) .
            ' | AT=' . basename($e->getFile()) . ':' . $e->getLine();
    }

    $line = $ts . ' | ' . $step .
        ' | ' . $method .
        ' | ' . $ip .
        ' | sid=' . $sid .
        ' | uri=' . $uri .
        $ctxTxt .
        $exTxt .
        PHP_EOL;

    @file_put_contents($file, $line, FILE_APPEND);
}

function gql(string $url, string $query, array $vars = [], ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_POSTFIELDS      => json_encode(
            ['query' => $query, 'variables' => $vars],
            JSON_UNESCAPED_UNICODE
        ),
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_TIMEOUT         => 20,
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

    return $json['data'] ?? [];
}

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

try {
    cb_log_line('start');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        cb_log_line('bad_method');
        throw new RuntimeException('Neplatný požadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');

    if ($email === '' || $heslo === '') {
        cb_log_line('missing_credentials', ['email' => $email, 'heslo_len' => (string)strlen($heslo)]);
        throw new RuntimeException('Vyplň email a heslo.');
    }

    cb_log_line('gql_login_request', ['email' => $email]);

    $login = gql(
        $GQL_URL,
        'query($email:String!,$password:String!){
            userLogin(email:$email,password:$password){
                accessToken
            }
        }',
        ['email' => $email, 'password' => $heslo]
    );

    $token = $login['userLogin']['accessToken'] ?? null;
    if (!is_string($token) || $token === '') {
        cb_log_line('no_token', ['email' => $email]);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }

    cb_log_line('gql_login_ok', ['email' => $email, 'token_len' => (string)strlen($token)]);

    cb_log_line('gql_me_request', ['email' => $email]);

    $me = gql(
        $GQL_URL,
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
            }
        }',
        [],
        $token
    );

    $u = $me['userGetLogged'] ?? null;
    if (!is_array($u) || empty($u['id']) || empty($u['email'])) {
        cb_log_line('me_invalid', ['email' => $email]);
        throw new RuntimeException('Nepodařilo se načíst profil uživatele.');
    }

    cb_log_line('gql_me_ok', ['id_user' => (string)(int)$u['id'], 'email' => (string)$u['email']]);

    $_SESSION['cb_user'] = [
        'id_user'   => (int)$u['id'],
        'name'      => (string)($u['name'] ?? ''),
        'surname'   => (string)($u['surname'] ?? ''),
        'email'     => (string)$u['email'],
        'telefon'   => (string)($u['phoneNumber'] ?? ''),
        'active'    => (bool)($u['active'] ?? false),
        'approved'  => (bool)($u['approved'] ?? false),
    ];

    // NOVĚ: token do session (pouze pro další GraphQL dotazy, nikam se neloguje ani neukládá do txt)
    $_SESSION['cb_token'] = $token;

    // NOVĚ: po úspěšném loginu spustíme průzkum API a zápis do pomocne/data_smeny.txt
    try {
        require_once __DIR__ . '/../pomocne/cteni_dat.php';
    } catch (Throwable $e) {
        cb_log_line('cteni_dat_fail', ['email' => $email], $e);
    }

    $_SESSION['cb_flash'] = 'Přihlášení OK';

    cb_log_line('redirect_ok', ['to' => cb_url('index.php?page=uvod'), 'id_user' => (string)(int)$u['id']]);

    header('Location: ' . cb_url('index.php?page=uvod'));
    exit;

} catch (Throwable $e) {
    cb_log_line('error', ['email' => (string)($_POST['email'] ?? '')], $e);

    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    $_SESSION['cb_flash'] = $e->getMessage();

    cb_log_line('redirect_fail', ['to' => cb_url('index.php?page=uvod')]);

    header('Location: ' . cb_url('index.php?page=uvod'));
    exit;
}

/* lib/login_smeny.php V9 – počet řádků: 238 – aktuální čas v ČR: 7.2.2026 */