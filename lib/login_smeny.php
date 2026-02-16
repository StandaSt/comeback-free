<?php
// lib/login_smeny.php * Verze: V16 * Aktualizace: 16.2.2026
declare(strict_types=1);

/*
 * PŘIHLÁŠENÍ PŘES SMĚNY (GraphQL API)
 *
 * Co to dělá:
 * - ověří email/heslo přes Směny (GraphQL)
 * - načte profil, role/sloty a pobočky
 * - uloží data do session
 * - zavolá DB sync (db/db_user_login.php)
 * - nastaví session pro časovač neaktivity (timeout + start + poslední aktivita)
 * - redirect na úvod
 *
 * Důležité:
 * - timeout neaktivity je jedna hodnota pro celý systém (zatím natvrdo zde).
 *   Později se sem napojí načtení z administrace (DB).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';
require_once __DIR__ . '/smeny_graphql.php';
require_once __DIR__ . '/user_bad_login.php';

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

/*
 * Timeout neaktivity (minuty) – JEDINÝ zdroj hodnoty.
 * - pokud chceš testovat rychlejší odhlášení, změň jen tady (např. 2).
 */
$CB_TIMEOUT_MIN = 2;

function post_str(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

try {
    cb_login_log_line('start');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        cb_login_log_line('bad_method');
        throw new RuntimeException('Neplatný požadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');

    if ($email === '' || $heslo === '') {
        cb_login_log_line('missing_credentials', [
            'email' => $email,
            'heslo_len' => (string)strlen($heslo),
        ]);
        throw new RuntimeException('Vyplň email a heslo.');
    }

    cb_login_log_line('gql_login_request', ['email' => $email]);

    try {
        $login = cb_smeny_graphql(
            $GQL_URL,
            'query($email:String!,$password:String!){
                userLogin(email:$email,password:$password){
                    accessToken
                }
            }',
            ['email' => $email, 'password' => $heslo]
        );
    } catch (Throwable $e) {
        cb_login_log_line('gql_login_fail', ['email' => $email], $e);
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }

    $token = $login['userLogin']['accessToken'] ?? null;
    if (!is_string($token) || $token === '') {
        cb_login_log_line('no_token', ['email' => $email]);
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }

    cb_login_log_line('gql_login_ok', [
        'email' => $email,
        'token_len' => (string)strlen($token),
    ]);

    cb_login_log_line('gql_me_request', ['email' => $email]);

    $me = cb_smeny_graphql(
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
        cb_login_log_line('me_invalid', ['email' => $email]);
        throw new RuntimeException('Nepodařilo se načíst profil uživatele.');
    }

    $idUser = (int)$u['id'];

    cb_login_log_line('gql_me_ok', [
        'id_user' => (string)$idUser,
        'email' => (string)$u['email'],
    ]);

    cb_login_log_line('gql_roles_slot_request', ['id_user' => (string)$idUser]);

    $rs = cb_smeny_graphql(
        $GQL_URL,
        'query{
            userGetLogged{
                id
                roles{ id name }
                shiftRoleTypeNames
            }
        }',
        [],
        $token
    );

    $rsUser = $rs['userGetLogged'] ?? null;
    if (!is_array($rsUser)) {
        cb_login_log_line('roles_slot_invalid', ['id_user' => (string)$idUser]);
        throw new RuntimeException('Nepodařilo se načíst role/sloty uživatele.');
    }

    $roles = $rsUser['roles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }

    $sloty = $rsUser['shiftRoleTypeNames'] ?? [];
    if (!is_array($sloty)) {
        $sloty = [];
    }

    $u['roles'] = $roles;
    $u['shiftRoleTypeNames'] = $sloty;

    cb_login_log_line('gql_roles_slot_ok', [
        'id_user' => (string)$idUser,
        'roles_count' => (string)count($roles),
        'sloty_count' => (string)count($sloty),
    ]);

    cb_login_log_line('gql_branches_request', ['id_user' => (string)$idUser]);

    $br = cb_smeny_graphql(
        $GQL_URL,
        'query($id:Int!){
            actionHistoryFindById(id:$id){
                user{
                    workingBranchNames
                    mainBranchName
                }
            }
        }',
        ['id' => $idUser],
        $token
    );

    $brUser = $br['actionHistoryFindById']['user'] ?? null;
    if (!is_array($brUser)) {
        cb_login_log_line('branches_invalid', ['id_user' => (string)$idUser]);
        throw new RuntimeException('Nepodařilo se načíst pobočky uživatele.');
    }

    $working = $brUser['workingBranchNames'] ?? [];
    $main = $brUser['mainBranchName'] ?? null;
    if (!is_array($working)) {
        $working = [];
    }

    $mainTxt = 'null';
    if (is_string($main)) {
        $mainTxt = $main;
    }

    cb_login_log_line('gql_branches_ok', [
        'id_user' => (string)$idUser,
        'working_count' => (string)count($working),
        'main' => $mainTxt,
    ]);

    // Token pro další volání Směn (zatím interní, později se možná omezí)
    $_SESSION['cb_token'] = $token;

    // Základní user data pro UI
    $_SESSION['cb_user'] = [
        'id_user'   => $idUser,
        'name'      => (string)($u['name'] ?? ''),
        'surname'   => (string)($u['surname'] ?? ''),
        'email'     => (string)$u['email'],
        'telefon'   => (string)($u['phoneNumber'] ?? ''),
        'active'    => (bool)($u['active'] ?? false),
        'approved'  => (bool)($u['approved'] ?? false),
        'roles'     => $roles,
        'sloty'     => $sloty,
    ];

    // Plný profil pro DB sync (bere si z toho role i sloty)
    $_SESSION['cb_user_profile'] = $u;

    // Pobočky pro DB sync
    $_SESSION['cb_user_branches'] = [
        'workingBranchNames' => $working,
        'mainBranchName' => $main,
    ];

    require_once __DIR__ . '/zapis_dat_txt.php';

    // DB sync po loginu (transakce + user_login + user_spy + role/sloty/povolení…)
    require_once __DIR__ . '/../db/db_user_login.php';
    cb_db_user_login();

    /*
     * ČASOVAČ (neaktivita) – session hodnoty pro login blok + JS
     *
     * Proč až tady:
     * - chceme mít jistotu, že po DB syncu je session v konzistentním stavu
     * - a že nic dalšího už tyto hodnoty nepřepíše
     */
    $_SESSION['cb_timeout_min'] = (int)$CB_TIMEOUT_MIN;
    $_SESSION['cb_session_start_ts'] = time();
    $_SESSION['cb_last_activity_ts'] = time();

    $_SESSION['login_ok'] = 1;
    $_SESSION['cb_flash'] = 'Přihlášení OK';

    cb_login_log_line('redirect_ok', [
        'to' => cb_url(''),
        'id_user' => (string)$idUser,
    ]);

    header('Location: ' . cb_url(''));
    exit;

} catch (Throwable $e) {
    cb_login_log_line('error', ['email' => (string)($_POST['email'] ?? '')], $e);

    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    unset($_SESSION['cb_user_profile']);
    unset($_SESSION['cb_user_branches']);

    // i kdyby něco z časovače existovalo, po chybě loginu to nesmí zůstat
    unset($_SESSION['cb_timeout_min']);
    unset($_SESSION['cb_session_start_ts']);
    unset($_SESSION['cb_last_activity_ts']);

    $_SESSION['cb_flash'] = $e->getMessage();

    cb_login_log_line('redirect_fail', ['to' => cb_url('')]);

    header('Location: ' . cb_url(''));
    exit;
}

/* lib/login_smeny.php * Verze: V16 * Aktualizace: 16.2.2026 * Počet řádků: 277 */
// Konec souboru