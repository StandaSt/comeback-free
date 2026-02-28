<?php
// lib/login_smeny.php * Verze: V23 * Aktualizace: 27.2.2026
declare(strict_types=1);

/*
 * PŘIHLÁŠENÍ PŘES SMĚNY (GraphQL API) + 2FA (schválení na mobilu)
 *
 * Co to dělá:
 * - ověří email/heslo přes Směny (GraphQL)
 * - načte profil + role/sloty (1 dotaz)
 * - načte pobočky (workingBranchNames) přes userGetLogged (2. dotaz)
 * - uloží data do session (bez login_ok)
 * - zavolá DB sync (db/db_user_login.php)
 * - připraví 2FA výzvu do DB (push_login_2fa) a odešle notifikaci na spárované zařízení (push_zarizeni)
 * - redirect na úvod (index.php zobrazí čekací modál)
 *
 * Důležité:
 * - login_ok se nastaví AŽ po schválení 2FA (mobil)
 * - LOCAL: 2FA se nepoužívá (notifikace z LOCAL nechodí) – po ověření ve Směnách se nastaví login_ok hned
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';
require_once __DIR__ . '/smeny_graphql.php';
require_once __DIR__ . '/user_bad_login.php';

require_once __DIR__ . '/push_send.php';

require_once __DIR__ . '/../db/db_api_smeny.php';

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

/*
 * Timeout neaktivity (minuty) – JEDINÝ zdroj hodnoty.
 */
$CB_TIMEOUT_MIN = 20;

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

    // 1) Sloučeno: profil + role + sloty
    cb_login_log_line('gql_me_rs_request', ['email' => $email]);

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
                roles{ id name }
                shiftRoleTypeNames
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

    $roles = $u['roles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }

    $sloty = $u['shiftRoleTypeNames'] ?? [];
    if (!is_array($sloty)) {
        $sloty = [];
    }

    cb_login_log_line('gql_me_rs_ok', [
        'id_user' => (string)$idUser,
        'roles_count' => (string)count($roles),
        'sloty_count' => (string)count($sloty),
    ]);

    // 2) Pobočky: workingBranchNames (seznam povolených poboček uživatele)
    cb_login_log_line('gql_branches_request', ['id_user' => (string)$idUser]);

    $br = cb_smeny_graphql(
        $GQL_URL,
        'query{
            userGetLogged{
                workingBranchNames
            }
        }',
        [],
        $token
    );

    $brUser = $br['userGetLogged'] ?? null;
    if (!is_array($brUser)) {
        cb_login_log_line('branches_invalid', ['id_user' => (string)$idUser]);
        throw new RuntimeException('Nepodařilo se načíst pobočky uživatele.');
    }

    $working = $brUser['workingBranchNames'] ?? [];
    if (!is_array($working)) {
        $working = [];
    }

    cb_login_log_line('gql_branches_ok', [
        'id_user' => (string)$idUser,
        'working_count' => (string)count($working),
    ]);

    $_SESSION['cb_token'] = $token;

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
        'mainBranchName' => null,
    ];

    require_once __DIR__ . '/zapis_dat_txt.php';

    require_once __DIR__ . '/../db/db_user_login.php';
    cb_db_user_login();

    $_SESSION['cb_timeout_min'] = (int)$CB_TIMEOUT_MIN;
    $_SESSION['cb_session_start_ts'] = time();
    $_SESSION['cb_last_activity_ts'] = time();

    // LOCAL: bez 2FA (notifikace z LOCAL nechodí) => rovnou přihlásit
    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') {
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_2fa_token']);

        header('Location: ' . cb_url(''));
        exit;
    }

    // ====== 2FA: vytvoř výzvu a čekej na schválení ======
    $limitSec = 300;
    if (defined('CB_2FA_LIMIT_SEC')) {
        $limitSec = (int)CB_2FA_LIMIT_SEC;
        if ($limitSec <= 0) {
            $limitSec = 300;
        }
    }

    // token je 64 hex znaků (32 bytes)
    $token2fa = bin2hex(random_bytes(32));

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') {
        $ip = 'UNKNOWN';
    }

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ua = trim($ua);
    if ($ua === '') {
        $ua = null;
    }

    $conn = db();
    $stmt = $conn->prepare('
        INSERT INTO push_login_2fa
        (id_user, token, stav, ip, prohlizec, vytvoreno, vyprsi, rozhodnuto, id_zarizeni)
        VALUES
        (?, ?, \'ceka\', ?, ?, NOW(), (NOW() + INTERVAL ? SECOND), NULL, NULL)
    ');
    if (!$stmt) {
        throw new RuntimeException('2FA: DB prepare selhal.');
    }

    $stmt->bind_param('isssi', $idUser, $token2fa, $ip, $ua, $limitSec);
    $stmt->execute();
    $stmt->close();

    // Ulož do session jen identifikátor aktuální 2FA výzvy
    $_SESSION['cb_2fa_token'] = $token2fa;

    // login_ok zatím NEEXISTUJE
    unset($_SESSION['login_ok']);

    // ====== Odeslání Web Push notifikace ======
    cb_login_log_line('2fa_push_send_start', [
        'id_user' => (string)$idUser,
    ]);

    $sent = cb_push_send_2fa($idUser, $token2fa);

    cb_login_log_line('2fa_push_send_done', [
        'id_user' => (string)$idUser,
        'sent' => $sent ? '1' : '0',
    ]);

    $_SESSION['cb_flash'] = 'Čekám na schválení přihlášení na mobilu';

    cb_login_log_line('2fa_wait', [
        'id_user' => (string)$idUser,
        'token_len' => (string)strlen($token2fa),
    ]);

    header('Location: ' . cb_url(''));
    exit;

} catch (Throwable $e) {

    /*
     * Neúspěšný login / chyba:
     * - zapíšeme log volání Směn i bez id_user a id_login (NULL)
     * - nic z toho nesmí shodit redirect ani chování loginu
     */
    try {
        db_api_smeny_flush(db(), null, null);
    } catch (Throwable $eLog) {
        error_log('api_smeny flush (fail) selhal: ' . $eLog->getMessage());
    }

    cb_login_log_line('error', ['email' => (string)($_POST['email'] ?? '')], $e);

    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    unset($_SESSION['cb_user_profile']);
    unset($_SESSION['cb_user_branches']);
    unset($_SESSION['cb_2fa_token']);

    unset($_SESSION['cb_timeout_min']);
    unset($_SESSION['cb_session_start_ts']);
    unset($_SESSION['cb_last_activity_ts']);

    $_SESSION['cb_flash'] = $e->getMessage();

    cb_login_log_line('redirect_fail', ['to' => cb_url('')]);

    header('Location: ' . cb_url(''));
    exit;
}

// lib/login_smeny.php * Verze: V23 * Aktualizace: 27.2.2026
// Počet řádků: 312
// Konec souboru