<?php
declare(strict_types=1);
/* Prihlasovani PINem podle user_set; pocatecni PIN vynucuje zmenu pred vstupem, chyby uvadeji pocet pokusu a presny konec blokace. */
require_once __DIR__ . '/../db/db_login_pin.php';
require_once __DIR__ . '/format.php';

/** Nula je platna cislice a prazdny retezec jako jediny znamena chybejici pocatecni PIN. */
function cb_pin_pocatecni(array $row): bool
{
    return (string)($row['pin'] ?? '') !== '';
}

/** Ucet ma PIN podle sveho zaznamu, nikdy podle pevneho ID nebo klientem poslane identity. */
function cb_pin_nastaven(array $row): bool
{
    return cb_pin_pocatecni($row) || trim((string)($row['pin_hash'] ?? '')) !== '';
}

/** Bez migrace nebo bez nastaveni zustava puvodni prihlasovaci tok. */
function cb_pin_nastaveni(mysqli $db, int $idUser): ?array
{
    if ($idUser <= 0 || !cb_db_pin_pripraven($db)) return null;
    return cb_db_pin_nacti($db, $idUser);
}

/** Otisk zneplatni rozpracovane prihlaseni i po rucnim prepsani pocatecniho PINu spravcem. */
function cb_pin_otisk(array $row): string
{
    return hash('sha256', json_encode([(string)($row['pin'] ?? ''), (string)($row['pin_hash'] ?? '')], JSON_THROW_ON_ERROR));
}

/** PIN je retezec presne ctyr cislic, aby zustaly zachovane pocatecni nuly. */
function cb_pin_format(string $pin): bool
{
    return preg_match('/^[0-9]{4}$/D', $pin) === 1;
}

/** Stejny konec blokace z DB se zobrazi pri pate chybe i pri dalsim pokusu behem blokace. */
function cb_pin_blokace_zprava(int $pokusy, int $do, int $ted): string
{
    return 'Chybné pokusy: ' . $pokusy . ' z 5. Ověření PINu je zablokováno do ' .
        cb_format('dcs', $do) . ' (zbývá ' . (int)ceil(max(0, $do - $ted) / 60) . ' min).';
}

/** Cista pravidla sdilene blokace; otevreny PIN ma prednost pred starym hashem. */
function cb_pin_vyhodnot(array $row, string $pin): array
{
    $now = (int)$row['ted'];
    if ((int)($row['blokace_do'] ?? 0) > $now) {
        return ['ok'=>false, 'zapsat'=>false,
            'chyba'=>cb_pin_blokace_zprava((int)$row['pin_pokusy'], (int)$row['blokace_do'], $now)];
    }
    if (!cb_pin_nastaven($row)) throw new RuntimeException('Přihlašovací PIN není nastaven.');
    $matches = cb_pin_pocatecni($row) ? hash_equals((string)$row['pin'], $pin) : password_verify($pin, (string)$row['pin_hash']);
    if (cb_pin_format($pin) && $matches) {
        return ['ok'=>true, 'zapsat'=>true, 'pokusy'=>0, 'okno'=>null, 'blokace'=>null];
    }
    $window = (int)($row['okno_od'] ?? 0);
    $fresh = $window > $now - 900 && $window <= $now && empty($row['blokace_do']);
    $attempts = ($fresh ? (int)$row['pin_pokusy'] : 0) + 1;
    return ['ok'=>false, 'zapsat'=>true, 'pokusy'=>$attempts, 'okno'=>$fresh ? $window : $now,
        'blokace'=>$attempts >= 5 ? $now + 900 : null,
        'chyba'=>$attempts >= 5 ? cb_pin_blokace_zprava($attempts, $now + 900, $now) :
            'PIN není správný. Chybné pokusy: ' . $attempts . ' z 5. Zbývající pokusy: ' . (5 - $attempts) . '.'];
}

/** Overeni i prvni zmena se serializuji nad radkem uctu; chybu pokusu hlasime az po commitu. */
function cb_pin_over(mysqli $db, int $idUser, string $pin, ?string $novyPin = null, ?string $ocekavanyOtisk = null, bool $prvniZmena = false): array
{
    if ($idUser <= 0 || !cb_db_pin_pripraven($db)) throw new RuntimeException('Přihlašovací PIN zatím není připraven.');
    if (!cb_db_pin_zamky($db)) throw new RuntimeException('PIN nelze bezpečně ověřit. Kontaktujte správce IS.');
    if ($novyPin !== null && !cb_pin_format($novyPin)) throw new RuntimeException('Nový PIN musí mít přesně čtyři číslice.');
    $db->begin_transaction();
    try {
        $row = cb_db_pin_nacti($db, $idUser, true);
        if ($row === null || !cb_pin_nastaven($row)) throw new RuntimeException('Přihlašovací PIN není nastaven.', 409);
        if ($ocekavanyOtisk !== null && !hash_equals($ocekavanyOtisk, cb_pin_otisk($row))) {
            throw new RuntimeException('PIN se mezitím změnil. Přihlaste se znovu.', 409);
        }
        // Preskoceni stareho PINu je mozne jen pri dokoncenem overeni v serverove vyzve a stejnem DB otisku.
        if ($prvniZmena && ($novyPin === null || $ocekavanyOtisk === null || !cb_pin_pocatecni($row))) {
            throw new RuntimeException('První změna PINu již není platná. Přihlaste se znovu.', 409);
        }
        $oldPin = $prvniZmena ? (string)$row['pin'] : $pin;
        $result = cb_pin_vyhodnot($row, $oldPin);
        if ($result['ok'] && $novyPin !== null && hash_equals($oldPin, $novyPin)) {
            throw new RuntimeException('Nový PIN musí být jiný než současný.');
        }
        if ($result['zapsat']) {
            $hash = $result['ok'] && $novyPin !== null ? password_hash($novyPin, PASSWORD_DEFAULT) : (string)($row['pin_hash'] ?? '');
            cb_db_pin_uloz($db, $idUser, $hash, $result['pokusy'], $result['okno'], $result['blokace'], $result['ok'] && $novyPin !== null);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
    if (!$result['ok']) throw new RuntimeException($result['chyba']);
    return ['zmena_nutna'=>cb_pin_pocatecni($row) && $novyPin === null];
}

/** Po platnem hesle zalozi pouze omezeny krok PIN a nezavisle se pokusi o normalni 2FA notifikaci. */
function cb_pin_zahaj(mysqli $db, array $user, bool $notifikovat = true): void
{
    $idUser = (int)($user['id_user'] ?? 0);
    if ($idUser <= 0 || (int)($user['aktivni'] ?? 0) !== 1) throw new RuntimeException('Nelze zahájit přihlášení PINem.');
    $row = cb_pin_nastaveni($db, $idUser);
    if ($row === null || !cb_pin_nastaven($row)) throw new CbUserVisibleException('Přihlašovací PIN není nastaven.');
    cb_session_forget_auth();
    unset($_SESSION['cb_local_login_user_id']);
    cb_session_regenerate_after_login();
    $_SESSION['cb_pin_ceka'] = ['id_user'=>$idUser, 'do'=>time()+600,
        'heslo_otisk'=>hash('sha256', (string)($user['heslo_hash'] ?? '')), 'pin_otisk'=>cb_pin_otisk($row), 'overeno'=>false];
    if ($notifikovat) {
        try {
            $idDevice = cb_duveryhodne_zarizeni_aktivni_id($db, $idUser);
            if ($idDevice > 0) {
                $token = bin2hex(random_bytes(32));
                cb_db_pin_push_vytvor($db, $idUser, $idDevice, $token);
                $_SESSION['cb_pin_ceka']['push_token'] = $token;
                if (!function_exists('cb_push_send_2fa')) require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
                cb_push_send_2fa($idUser, $token);
            }
        } catch (Throwable $error) {
            // Nedorucena notifikace nesmi zablokovat alternativni PIN; neukladame tajne udaje do logu.
        }
    }
}

/** Rozpracovane overeni nikdy samo o sobe neposkytuje pristup do IS ani registraci telefonu. */
function cb_pin_ceka(): bool
{
    $pending = $_SESSION['cb_pin_ceka'] ?? null;
    return is_array($pending) && (int)($pending['id_user'] ?? 0) > 0 && (int)($pending['do'] ?? 0) > time() && empty($_SESSION['login_ok']);
}

/** Centralni zabrana obejiti prvni zmeny pres push, QR, e-mail nebo duveryhodnou cookie. */
function cb_pin_pred_dokoncenim(mysqli $db, array $user): bool
{
    $row = cb_pin_nastaveni($db, (int)($user['id_user'] ?? 0));
    if ($row === null || !cb_pin_pocatecni($row)) return false;
    cb_pin_zahaj($db, $user, false);
    // Volajici uz overil jinou platnou cestu prihlaseni; povinna zmena zustava jedinym dalsim krokem.
    $_SESSION['cb_pin_ceka']['overeno'] = true;
    return true;
}

/** Nevyuzity push token po dokonceni nebo zruseni PIN kroku nenechavame cekajici. */
function cb_pin_push_uklid(mysqli $db): void
{
    $pending = $_SESSION['cb_pin_ceka'] ?? [];
    if (!empty($pending['push_token'])) {
        try { cb_db_pin_push_ukonci($db, (int)$pending['id_user'], (string)$pending['push_token']); } catch (Throwable $error) {}
    }
}
