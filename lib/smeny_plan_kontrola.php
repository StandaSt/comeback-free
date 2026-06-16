<?php
// lib/smeny_plan_kontrola.php
// Verze: V1
// Aktualizace: 16.06.2026
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

if (!function_exists('cb_smeny_plan_kontrola_get_secret')) {
    function cb_smeny_plan_kontrola_get_secret(string $key): string
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

if (!function_exists('cb_smeny_plan_kontrola_login')) {
    function cb_smeny_plan_kontrola_login(): string
    {
        $email = cb_smeny_plan_kontrola_get_secret('email');
        $heslo = cb_smeny_plan_kontrola_get_secret('heslo');

        if ($email === '' || $heslo === '') {
            throw new RuntimeException('Směny cron: chybí email nebo heslo v config/secrets.php.');
        }

        $login = cb_smeny_graphql(
            'https://smeny.pizzacomeback.cz/graphql',
            'query($email:String!,$password:String!){ userLogin(email:$email,password:$password){ accessToken } }',
            ['email' => $email, 'password' => $heslo]
        );

        $token = $login['userLogin']['accessToken'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Směny cron: nepodařilo se získat accessToken.');
        }

        return $token;
    }
}

if (!function_exists('cb_smeny_plan_kontrola_lock_name')) {
    function cb_smeny_plan_kontrola_lock_name(): string
    {
        return 'cb_smeny_plan_import';
    }
}

if (!function_exists('cb_smeny_plan_kontrola_lock_acquire')) {
    function cb_smeny_plan_kontrola_lock_acquire(mysqli $db, int $timeoutSec = 10): string
    {
        $lockName = cb_smeny_plan_kontrola_lock_name();
        $stmt = $db->prepare('SELECT GET_LOCK(?, ?) AS got_lock');
        if ($stmt === false) {
            throw new RuntimeException('Směny cron: nepodařilo se připravit DB zámek.');
        }
        $stmt->bind_param('si', $lockName, $timeoutSec);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if ((int)($row['got_lock'] ?? 0) !== 1) {
            throw new RuntimeException('Směny cron: nepodařilo se získat DB zámek.');
        }

        return $lockName;
    }
}

if (!function_exists('cb_smeny_plan_kontrola_lock_release')) {
    function cb_smeny_plan_kontrola_lock_release(mysqli $db, string $lockName): void
    {
        if ($lockName === '') {
            return;
        }

        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('s', $lockName);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_smeny_plan_kontrola')) {
    function cb_smeny_plan_kontrola(): void
    {
        $token = cb_smeny_plan_kontrola_login();

        $_SESSION['cb_token'] = $token;
        $GLOBALS['cb_smeny_plan_cron'] = true;
        $GLOBALS['cb_smeny_plan_import_all'] = true;

        $file = __DIR__ . '/../inicializace/plnime_smeny_plan.php';
        if (!file_exists($file)) {
            throw new RuntimeException('Směny cron: soubor nenalezen plnime_smeny_plan.php.');
        }

        $db = db();
        $lockName = '';

        try {
            $lockName = cb_smeny_plan_kontrola_lock_acquire($db, 10);
            include $file;
        } finally {
            cb_smeny_plan_kontrola_lock_release($db, $lockName);
        }
    }
}

if (!defined('CB_SMENY_PLAN_KONTROLA_AUTO_RUN') || CB_SMENY_PLAN_KONTROLA_AUTO_RUN !== false) {
    cb_smeny_plan_kontrola();
}
