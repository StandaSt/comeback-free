<?php
// lib/zapis_dat_txt.php * Verze: V2 * Aktualizace: 12.2.2026 * Počet řádků: 156
declare(strict_types=1);

/*
 * DIAGNOSTIKA SMĚNY – zápis do pomocne/data_smeny.txt
 *
 * Účel:
 * - tento soubor se volá z lib/login_smeny.php přes require_once
 * - NIC nestahuje z API, jen zapíše do txt to, co už máme v session
 *
 * Co zapisuje:
 * 1) USER:PROFILE   (plný profil z $_SESSION['cb_user_profile'])
 * 2) USER:BRANCHES  (working + main z $_SESSION['cb_user_branches'])
 * 3) USER:ROLES     (role přihlášeného uživatele; id+name ze Směn, pokud jsou v profilu)
 * 4) USER:SLOTY     (sloty přihlášeného uživatele; ve Směnách jsou to názvy shiftRoleTypeNames)
 *
 * Pozn.:
 * - dříve to bývalo v souboru cteni_dat.php a umělo tahat data z API
 * - teď je to čistě zápis diagnostiky do txt
 */

require_once __DIR__ . '/bootstrap.php';

$OUT_FILE = __DIR__ . '/../pomocne/data_smeny.txt';

/**
 * Připraví jednu řádku ve formátu:
 * "YYYY-mm-dd HH:ii:ss | LABEL | DATA"
 */
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

/**
 * Bezpečný append do souboru (vytvoří adresář, zamkne soubor)
 */
function cb_file_append(string $file, string $line): void
{
    @mkdir(dirname($file), 0775, true);
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Kontext pro RUN:START/END (aby byl v txt vždy dohledatelný uživatel)
 */
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

$ctx = cb_get_ctx();

cb_file_append($OUT_FILE, cb_txt_line('RUN:START', [
    'mode' => 'login_auto',
    'email' => $ctx['email'],
    'id_user' => $ctx['id_user'],
]));

// 1) profil (plný profil z login_smeny.php)
$profile = $_SESSION['cb_user_profile'] ?? null;
if (is_array($profile)) {
    cb_file_append($OUT_FILE, cb_txt_line('USER:PROFILE', $profile));
} else {
    cb_file_append($OUT_FILE, cb_txt_line('USER:PROFILE:ERROR', 'Chybí cb_user_profile v session.'));
}

// 2) pobočky (working + main)
$branches = $_SESSION['cb_user_branches'] ?? null;
if (is_array($branches)) {
    cb_file_append($OUT_FILE, cb_txt_line('USER:BRANCHES', [
        'workingBranchNames' => $branches['workingBranchNames'] ?? [],
        'mainBranchName' => $branches['mainBranchName'] ?? null,
    ]));
} else {
    cb_file_append($OUT_FILE, cb_txt_line('USER:BRANCHES:ERROR', 'Chybí cb_user_branches v session.'));
}

// 3) role (id + name ze Směn, pokud jsou v profilu)
if (is_array($profile)) {
    $roles = $profile['roles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }

    $rolesOut = [];
    foreach ($roles as $r) {
        if (!is_array($r)) {
            continue;
        }
        $rid = (int)($r['id'] ?? 0);
        $rname = trim((string)($r['name'] ?? ''));
        if ($rid <= 0 && $rname === '') {
            continue;
        }
        $rolesOut[] = ['id' => $rid, 'name' => $rname];
    }

    cb_file_append($OUT_FILE, cb_txt_line('USER:ROLES', $rolesOut));
} else {
    cb_file_append($OUT_FILE, cb_txt_line('USER:ROLES:ERROR', 'Chybí cb_user_profile v session.'));
}

// 4) sloty (ve Směnách jsou to jen názvy: shiftRoleTypeNames)
if (is_array($profile)) {
    $sloty = $profile['shiftRoleTypeNames'] ?? [];
    if (!is_array($sloty)) {
        $sloty = [];
    }

    $slotyOut = [];
    foreach ($sloty as $s) {
        $name = trim((string)$s);
        if ($name !== '') {
            $slotyOut[] = $name;
        }
    }

    cb_file_append($OUT_FILE, cb_txt_line('USER:SLOTY', $slotyOut));
} else {
    cb_file_append($OUT_FILE, cb_txt_line('USER:SLOTY:ERROR', 'Chybí cb_user_profile v session.'));
}

cb_file_append($OUT_FILE, cb_txt_line('RUN:END', ['mode' => 'login_auto']));

// lib/zapis_dat_txt.php * Verze: V2 * Aktualizace: 12.2.2026 * Počet řádků: 156
// Konec souboru