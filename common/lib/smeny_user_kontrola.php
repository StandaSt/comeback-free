<?php
// lib/smeny_user_kontrola.php
// Verze: V1
// Aktualizace: 18.06.2026
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
} else {
    require_once __DIR__ . '/session_boot.php';
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/smeny_graphql.php';

if (PHP_SAPI === 'cli') {
    $PROSTREDI = 'SERVER';
}

if (!function_exists('cb_smeny_user_session_snapshot')) {
    function cb_smeny_user_session_snapshot(): array
    {
        $keys = ['cb_token', 'cb_user', 'login_ok', 'cb_auth_ok', 'cb_2fa_token', 'cb_user_profile', 'cb_user_branches'];
        $snapshot = [];
        foreach ($keys as $key) {
            $snapshot[$key] = array_key_exists($key, $_SESSION) ? $_SESSION[$key] : null;
        }

        return $snapshot;
    }
}

if (!function_exists('cb_smeny_user_session_restore')) {
    function cb_smeny_user_session_restore(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            if ($value === null) {
                unset($_SESSION[$key]);
                continue;
            }
            $_SESSION[$key] = $value;
        }
    }
}

if (!function_exists('cb_smeny_user_kontrola_get_secret')) {
    function cb_smeny_user_kontrola_get_secret(string $key): string
    {
        $secrets = $GLOBALS['SECRETS'] ?? null;
        if (!is_array($secrets)) {
            return '';
        }

        $smeny = $secrets['smeny'] ?? null;
        if (!is_array($smeny)) {
            return '';
        }

        return trim((string)($smeny[$key] ?? ''));
    }
}

if (!function_exists('cb_smeny_user_kontrola_login')) {
    function cb_smeny_user_kontrola_login(): string
    {
        $email = cb_smeny_user_kontrola_get_secret('email');
        $heslo = cb_smeny_user_kontrola_get_secret('heslo');

        if ($email === '' || $heslo === '') {
            throw new RuntimeException('Směny cron user: chybí email nebo heslo v config/secrets.php.');
        }

        $login = cb_smeny_graphql(
            'https://smeny.pizzacomeback.cz/graphql',
            'query($email:String!,$password:String!){ userLogin(email:$email,password:$password){ accessToken } }',
            ['email' => $email, 'password' => $heslo]
        );

        $token = $login['userLogin']['accessToken'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Směny cron user: nepodařilo se získat accessToken.');
        }

        $me = cb_smeny_graphql(
            'https://smeny.pizzacomeback.cz/graphql',
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
            throw new RuntimeException('Směny cron user: nepodařilo se načíst profil přihlášeného uživatele.');
        }

        $_SESSION['cb_user'] = [
            'id_user' => (int)$u['id'],
            'name' => (string)($u['name'] ?? ''),
            'surname' => (string)($u['surname'] ?? ''),
            'email' => (string)($u['email'] ?? ''),
            'telefon' => (string)($u['phoneNumber'] ?? ''),
            'active' => (bool)($u['active'] ?? false),
            'approved' => (bool)($u['approved'] ?? false),
            'roles' => [],
            'sloty' => [],
        ];
        $_SESSION['login_ok'] = 1;

        return $token;
    }
}

if (!function_exists('cb_smeny_user_kontrola')) {
    function cb_smeny_user_kontrola(bool $preserveSession = false): void
    {
        $snapshot = $preserveSession ? cb_smeny_user_session_snapshot() : [];

        try {
            $token = cb_smeny_user_kontrola_login();

            $_SESSION['cb_token'] = $token;
            $GLOBALS['cb_smeny_user_cron'] = true;

            $file = __DIR__ . '/../../provoz/inicializace/plnime_smeny_user.php';
            if (!file_exists($file)) {
                throw new RuntimeException('Směny cron user: soubor nenalezen plnime_smeny_user.php.');
            }

            include $file;
        } finally {
            if ($preserveSession) {
                cb_smeny_user_session_restore($snapshot);
            }
        }
    }
}

if (!defined('CB_SMENY_USER_KONTROLA_AUTO_RUN') || CB_SMENY_USER_KONTROLA_AUTO_RUN !== false) {
    cb_smeny_user_kontrola();
}
