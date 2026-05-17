<?php
// lib/login_smeny.php * Verze: V25 * Aktualizace: 31.03.2026
declare(strict_types=1);

/*
 * PĹIHLĂĹ ENĂŤ PĹES SMÄšNY (GraphQL API) + 2FA (schvĂˇlenĂ­ na mobilu)
 *
 * Co to dÄ›lĂˇ:
 * - ovÄ›Ĺ™Ă­ email/heslo pĹ™es SmÄ›ny (GraphQL)
 * - naÄŤte jen zĂˇkladnĂ­ profil pro modĂˇl registrace / 2FA
 * - uloĹľĂ­ minimĂˇlnĂ­ data do session (bez login_ok)
 * - pĹ™i prvnĂ­m loginu bez aktivnĂ­ho zaĹ™Ă­zenĂ­ pĹ™eskoÄŤĂ­ 2FA a pustĂ­ uĹľivatele do pĂˇrovĂˇnĂ­ mobilu
 * - pĹ™i dalĹˇĂ­m loginu pĹ™ipravĂ­ 2FA vĂ˝zvu do DB (push_login_2fa) a odeĹˇle notifikaci na spĂˇrovanĂ© zaĹ™Ă­zenĂ­ (push_zarizeni)
 * - redirect na Ăşvod (index.php zobrazĂ­ ÄŤekacĂ­ modĂˇl nebo modĂˇl pĂˇrovĂˇnĂ­)
 *
 * DĹŻleĹľitĂ©:
 * - login_ok se nastavĂ­ AĹ˝ po schvĂˇlenĂ­ 2FA (mobil), nebo hned pĹ™i LOCAL / prvnĂ­m loginu bez zaĹ™Ă­zenĂ­
 * - LOCAL: 2FA lze vypnout pĹ™es set_system.on_2fa (notifikace z LOCAL nechodĂ­)
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/smeny_graphql.php';
require_once __DIR__ . '/user_bad_login.php';

require_once __DIR__ . '/../notifikace/notifikace_2fa.php';

require_once __DIR__ . '/../db/db_api_smeny.php';

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

/*
 * Timeout neaktivity (minuty) â€“ JEDINĂť zdroj hodnoty.
 */

function post_str(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('NeplatnĂ˝ poĹľadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');

    if ($email === '' || $heslo === '') {
        throw new RuntimeException('VyplĹ email a heslo.');
    }


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
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('NeplatnĂ© pĹ™ihlaĹˇovacĂ­ Ăşdaje.');
    }

    $token = $login['userLogin']['accessToken'] ?? null;
    if (!is_string($token) || $token === '') {
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('NeplatnĂ© pĹ™ihlaĹˇovacĂ­ Ăşdaje.');
    }



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
            }
        }',
        [],
        $token
    );

    $u = $me['userGetLogged'] ?? null;
    if (!is_array($u) || empty($u['id']) || empty($u['email'])) {
        throw new RuntimeException('NepodaĹ™ilo se naÄŤĂ­st profil uĹľivatele.');
    }

    $idUser = (int)$u['id'];


    $_SESSION['cb_token'] = $token;

    $_SESSION['cb_user'] = [
        'id_user'   => $idUser,
        'name'      => (string)($u['name'] ?? ''),
        'surname'   => (string)($u['surname'] ?? ''),
        'email'     => (string)($u['email'] ?? ''),
        'telefon'   => (string)($u['phoneNumber'] ?? ''),
        'active'    => (bool)($u['active'] ?? false),
        'approved'  => (bool)($u['approved'] ?? false),
        'roles'     => [],
        'sloty'     => [],
    ];

    $_SESSION['cb_auth_ok'] = 1;

    // LOCAL: 2FA se pĹ™eskoÄŤĂ­ jen kdyĹľ je vypnuto v set_system.on_2fa
    cb_login_load_settings_to_session($idUser);
    $on2fa = (int)cb_system_setting('on_2fa', 1);

    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL' || $on2fa !== 1) {
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
        unset($_SESSION['cb_2fa_token']);

        cb_login_finalize_after_ok($token);
        $_SESSION['cb_initial_loader_text'] = 'Inicializace systĂ©mu ...';

        header('Location: ' . cb_url(''));
        exit;
    }

    // SERVER: bez aktivnĂ­ho zaĹ™Ă­zenĂ­ je to prvnĂ­ login => pĹ™eskoÄŤ 2FA a pusĹĄ pĂˇrovĂˇnĂ­
    $maAktivniZarizeni = false;

    $stmtDevice = db()->prepare('
        SELECT id
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        LIMIT 1
    ');

    if ($stmtDevice) {
        $stmtDevice->bind_param('i', $idUser);
        $stmtDevice->execute();
        $stmtDevice->store_result();
        $maAktivniZarizeni = ($stmtDevice->num_rows > 0);
        $stmtDevice->close();
    }

    if (!$maAktivniZarizeni) {
        unset($_SESSION['login_ok']);
        unset($_SESSION['cb_2fa_token']);


        header('Location: ' . cb_url(''));
        exit;
    }

    // ====== 2FA: vytvoĹ™ vĂ˝zvu a ÄŤekej na schvĂˇlenĂ­ ======
    $limitSec = 300;
    if (defined('CB_2FA_LIMIT_SEC')) {
        $limitSec = (int)CB_2FA_LIMIT_SEC;
        if ($limitSec <= 0) {
            $limitSec = 300;
        }
    }

    // token je 64 hex znakĹŻ (32 bytes)
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

    // UloĹľ do session jen identifikĂˇtor aktuĂˇlnĂ­ 2FA vĂ˝zvy
    $_SESSION['cb_2fa_token'] = $token2fa;

    // login_ok zatĂ­m NEEXISTUJE
    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_auth_ok']);

    // ====== OdeslĂˇnĂ­ Web Push notifikace ======

    $sent = cb_push_send_2fa($idUser, $token2fa);


    $_SESSION['cb_flash'] = 'ÄŚekĂˇm na schvĂˇlenĂ­ pĹ™ihlĂˇĹˇenĂ­ na mobilu';


    header('Location: ' . cb_url(''));
    exit;

} catch (Throwable $e) {

    /*
     * NeĂşspÄ›ĹˇnĂ˝ login / chyba:
     * - zapĂ­Ĺˇeme log volĂˇnĂ­ SmÄ›n i bez id_user a id_login (NULL)
     * - nic z toho nesmĂ­ shodit redirect ani chovĂˇnĂ­ loginu
     */
    try {
        db_api_smeny_flush(db(), null, null);
    } catch (Throwable $eLog) {
        error_log('api_smeny flush (fail) selhal: ' . $eLog->getMessage());
    }


    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    unset($_SESSION['cb_user_profile']);
    unset($_SESSION['cb_user_branches']);
    unset($_SESSION['cb_2fa_token']);
    unset($_SESSION['cb_auth_ok']);
    unset($_SESSION['cb_system']);
    unset($_SESSION['cb_user_settings']);

    unset($_SESSION['cb_timeout_min']);
    unset($_SESSION['cb_session_start_ts']);
    unset($_SESSION['cb_last_activity_ts']);

    $_SESSION['cb_flash'] = $e->getMessage();


    header('Location: ' . cb_url(''));
    exit;
}

// lib/login_smeny.php * Verze: V25 * Aktualizace: 30.03.2026
// PoÄŤet Ĺ™ĂˇdkĹŻ: 359
// Konec souboru
