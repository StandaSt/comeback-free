<?php
// lib/login_smeny.php * Verze: V11 * Aktualizace: 12.2.2026 * Počet řádků: 296
declare(strict_types=1);

/*
 * PŘIHLÁŠENÍ PŘES SMĚNY (GraphQL API)
 *
 * Co to je:
 * - jediný „spouštěcí“ skript pro login uživatele proti systému Směny
 * - po úspěchu uloží do session všechno potřebné (token + data uživatele)
 * - po uložení do session už NIKDY znovu nevolá Směny (API) v rámci tohoto loginu
 *
 * Tok (kroky):
 * 1) POST email + heslo
 * 2) Směny: userLogin -> accessToken (token)
 * 3) Směny: userGetLogged -> profil (tady vzniká id_user)
 * 4) Směny: userGetLogged -> role + sloty (jen pro přihlášeného uživatele)
 * 5) Směny: actionHistoryFindById -> workingBranchNames + mainBranchName
 * 6) session:
 *    - cb_token
 *    - cb_user            (základ pro UI; rychlé a malé)
 *    - cb_user_profile    (plný profil pro DB + txt; včetně rolí a slotů)
 *    - cb_user_branches   (pobočky pro DB + txt)
 * 7) lib/zapis_dat_txt.php    (jen diagnostika do pomocne/data_smeny.txt; BEZ dalšího API)
 * 8) lib/db_user_login.php    (srovnání DB; BEZ dalšího API)
 * 9) login_ok = 1, redirect
 *
 * Důležité zásady:
 * - heslo se nikdy neloguje
 * - po chybě nesmí zůstat mezistav v session
 * - cílem je držet login „atomický“: buď projde celý, nebo neprojde nic
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';
require_once __DIR__ . '/smeny_graphql.php';
require_once __DIR__ . '/user_bad_login.php';

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

/**
 * Bezpečné čtení stringu z POST.
 * - vrací vždy string (i kdyby v POST nebylo nic)
 * - trim() kvůli mezerám
 */
function post_str(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

try {
    cb_login_log_line('start');

    // Ochrana: očekáváme jen POST (z formuláře login stránky)
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        cb_login_log_line('bad_method');
        throw new RuntimeException('Neplatný požadavek.');
    }

    // Vstupy z formuláře
    $email = post_str('email');
    $heslo = post_str('heslo');

    // Základní kontrola (bez toho nemá cenu volat Směny)
    if ($email === '' || $heslo === '') {
        cb_login_log_line('missing_credentials', [
            'email' => $email,
            'heslo_len' => (string)strlen($heslo), // jen délka, nikdy ne samotné heslo
        ]);
        throw new RuntimeException('Vyplň email a heslo.');
    }

    // 1) token (přihlášení do Směn)
    cb_login_log_line('gql_login_request', ['email' => $email]);

    $login = cb_smeny_graphql(
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
        // Neplatné přihlášení: zalogujeme do DB tabulky user_bad_login (heslo se loguje jako hash v tom helperu)
        cb_login_log_line('no_token', ['email' => $email]);
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }

    cb_login_log_line('gql_login_ok', [
        'email' => $email,
        'token_len' => (string)strlen($token),
    ]);

    // 2) profil (tady vzniká id_user)
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

    // 3) role + sloty (jen pro přihlášeného uživatele)
    //
    // Role:
    // - Směny vrací u uživatele "roles" jako pole objektů Role { id, name }
    //
    // Sloty:
    // - ve Směnách je to "shiftRoleTypeNames" (pole názvů: Instor, Kurýr, ...)
    //
    // Pozn.: Záměrně nevoláme žádné FindAll (číselníky). Chceme data jen pro přihlášeného uživatele.
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

    // roles = pole objektů (id,name)
    $roles = $rsUser['roles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }

    // shiftRoleTypeNames = pole stringů (názvy slotů)
    $sloty = $rsUser['shiftRoleTypeNames'] ?? [];
    if (!is_array($sloty)) {
        $sloty = [];
    }

    // Uložíme do profilu (plná data pro DB + txt), ať je vše na jednom místě
    $u['roles'] = $roles;
    $u['shiftRoleTypeNames'] = $sloty;

    cb_login_log_line('gql_roles_slot_ok', [
        'id_user' => (string)$idUser,
        'roles_count' => (string)count($roles),
        'sloty_count' => (string)count($sloty),
    ]);

    // 4) pobočky (working + main)
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

    cb_login_log_line('gql_branches_ok', [
        'id_user' => (string)$idUser,
        'working_count' => (string)count($working),
        'main' => is_string($main) ? $main : 'null',
    ]);

    // --- SESSION: uložíme data ze Směn (od teď už nic z API znovu netaháme) ---
    //
    // cb_token:
    // - token pro Směny (používá se jen tam, kde je to výslovně povoleno)
    $_SESSION['cb_token'] = $token;

    // cb_user:
    // - malé a rychlé info pro UI (např. hlavička, menu, apod.)
    // - role a sloty jsou tu kvůli řízení práv v IS
    $_SESSION['cb_user'] = [
        'id_user'   => $idUser,
        'name'      => (string)($u['name'] ?? ''),
        'surname'   => (string)($u['surname'] ?? ''),
        'email'     => (string)$u['email'],
        'telefon'   => (string)($u['phoneNumber'] ?? ''),
        'active'    => (bool)($u['active'] ?? false),
        'approved'  => (bool)($u['approved'] ?? false),

        // role = pole objektů Role {id, name}
        'roles'     => $roles,

        // sloty = pole názvů slotů (shiftRoleTypeNames)
        'sloty'     => $sloty,
    ];

    // cb_user_profile:
    // - plný profil pro DB + txt
    // - včetně rolí a slotů (aby se při loginu nic dalšího z API nemusel tahat)
    $_SESSION['cb_user_profile'] = $u;

    // cb_user_branches:
    // - pobočky pro DB + txt
    $_SESSION['cb_user_branches'] = [
        'workingBranchNames' => $working,
        'mainBranchName' => $main,
    ];

    // 5) diagnostika do txt (bez API)
    require_once __DIR__ . '/zapis_dat_txt.php';

    // 6) DB sync (bez API)
    require_once __DIR__ . '/db_user_login.php';
    cb_db_user_login();

    // 7) hotovo
    $_SESSION['login_ok'] = 1;
    $_SESSION['cb_flash'] = 'Přihlášení OK';

    cb_login_log_line('redirect_ok', [
        'to' => cb_url('index.php?page=uvod'),
        'id_user' => (string)$idUser,
    ]);

    header('Location: ' . cb_url('index.php?page=uvod'));
    exit;

} catch (Throwable $e) {
    cb_login_log_line('error', ['email' => (string)($_POST['email'] ?? '')], $e);

    // Vyčistit login session (žádný mezistav)
    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    unset($_SESSION['cb_user_profile']);
    unset($_SESSION['cb_user_branches']);

    $_SESSION['cb_flash'] = $e->getMessage();

    cb_login_log_line('redirect_fail', ['to' => cb_url('index.php?page=uvod')]);

    header('Location: ' . cb_url('index.php?page=uvod'));
    exit;
}

// lib/login_smeny.php * Verze: V11 * Aktualizace: 12.2.2026 * Počet řádků: 296
// Konec souboru