<?php
declare(strict_types=1);

/*
 * Ucel souboru: Bezpecne rozpoznani registrovaneho zarizeni pri prihlaseni.
 * Cookie obsahuje pouze podepsany identifikator aktivniho push zarizeni.
 * Platnost cookie se vzdy overuje proti aktualnimu zaznamu v push_zarizeni.
 */

/* Vrati host-only nazev cookie; na HTTPS pouzije bezpecnostni prefix __Host. */
function cb_duveryhodne_zarizeni_cookie_nazev(): string
{
    $sessionCookie = session_get_cookie_params();
    return !empty($sessionCookie['secure'])
        ? '__Host-cb_duveryhodne_zarizeni'
        : 'cb_duveryhodne_zarizeni';
}

/* Odvodi samostatny podpisovy klic z existujiciho serveroveho VAPID klice. */
function cb_duveryhodne_zarizeni_podpisovy_klic(): string
{
    if (!defined('CB_VAPID_PRIVATE') || trim((string)CB_VAPID_PRIVATE) === '') {
        return '';
    }

    return hash_hmac('sha256', 'comeback-duveryhodne-zarizeni-v1', (string)CB_VAPID_PRIVATE, true);
}

/* Vytvori podpis cookie svazany s uzivatelem, zarizenim, expiraci a push endpointem. */
function cb_duveryhodne_zarizeni_podpis(int $idUser, int $idZarizeni, int $expirace, string $endpointHash): string
{
    $klic = cb_duveryhodne_zarizeni_podpisovy_klic();
    if ($klic === '') {
        return '';
    }

    $data = $idUser . '|' . $idZarizeni . '|' . $expirace . '|' . strtoupper($endpointHash);
    return hash_hmac('sha256', $data, $klic);
}

/* Najde aktivni zarizeni uzivatele podle push endpointu z aktualniho prohlizece. */
function cb_duveryhodne_zarizeni_podle_endpointu(mysqli $db, int $idUser, string $endpoint): int
{
    $endpoint = trim($endpoint);
    if ($idUser <= 0 || strlen($endpoint) < 20 || strlen($endpoint) > 2048) {
        return 0;
    }

    $stmt = $db->prepare('SELECT id FROM push_zarizeni WHERE id_user=? AND endpoint_hash=UNHEX(SHA2(?,256)) AND aktivni=1 LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('is', $idUser, $endpoint);
    $stmt->execute();
    $stmt->bind_result($idZarizeni);
    $nalezeno = $stmt->fetch();
    $stmt->close();

    return $nalezeno ? (int)$idZarizeni : 0;
}

/* Vrati prvni aktivni zarizeni uzivatele pro bezny 2FA tok. */
function cb_duveryhodne_zarizeni_aktivni_id(mysqli $db, int $idUser): int
{
    if ($idUser <= 0) {
        return 0;
    }

    $stmt = $db->prepare('SELECT id FROM push_zarizeni WHERE id_user=? AND aktivni=1 ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->bind_result($idZarizeni);
    $nalezeno = $stmt->fetch();
    $stmt->close();

    return $nalezeno ? (int)$idZarizeni : 0;
}

/* Nacte hash endpointu konkretniho aktivniho zarizeni. */
function cb_duveryhodne_zarizeni_endpoint_hash(mysqli $db, int $idUser, int $idZarizeni): string
{
    if ($idUser <= 0 || $idZarizeni <= 0) {
        return '';
    }

    $stmt = $db->prepare('SELECT HEX(endpoint_hash) FROM push_zarizeni WHERE id=? AND id_user=? AND aktivni=1 LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('ii', $idZarizeni, $idUser);
    $stmt->execute();
    $stmt->bind_result($endpointHash);
    $nalezeno = $stmt->fetch();
    $stmt->close();

    return $nalezeno ? strtoupper((string)$endpointHash) : '';
}

/* Ulozi podepsanou cookie pouze pro existujici aktivni zarizeni uzivatele. */
function cb_duveryhodne_zarizeni_uloz_cookie(mysqli $db, int $idUser, int $idZarizeni): bool
{
    $endpointHash = cb_duveryhodne_zarizeni_endpoint_hash($db, $idUser, $idZarizeni);
    if ($endpointHash === '') {
        return false;
    }

    $expirace = time() + (365 * 24 * 60 * 60);
    $podpis = cb_duveryhodne_zarizeni_podpis($idUser, $idZarizeni, $expirace, $endpointHash);
    if ($podpis === '') {
        return false;
    }

    $sessionCookie = session_get_cookie_params();
    $hodnota = 'v1.' . $idZarizeni . '.' . $expirace . '.' . $podpis;
    $ulozeno = setcookie(cb_duveryhodne_zarizeni_cookie_nazev(), $hodnota, [
        'expires' => $expirace,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($sessionCookie['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($ulozeno) {
        $stmt = $db->prepare('UPDATE push_zarizeni SET naposledy=NOW() WHERE id=? AND id_user=? AND aktivni=1');
        if ($stmt) {
            $stmt->bind_param('ii', $idZarizeni, $idUser);
            $stmt->execute();
            $stmt->close();
        }
    }

    return $ulozeno;
}

/* Overi podepsanou cookie a vrati id aktivniho zarizeni daneho uzivatele. */
function cb_duveryhodne_zarizeni_cookie_id(mysqli $db, int $idUser): int
{
    $cookie = trim((string)($_COOKIE[cb_duveryhodne_zarizeni_cookie_nazev()] ?? ''));
    if ($idUser <= 0 || $cookie === '') {
        return 0;
    }

    $casti = explode('.', $cookie);
    if (count($casti) !== 4 || $casti[0] !== 'v1' || !ctype_digit($casti[1]) || !ctype_digit($casti[2]) || !preg_match('/^[a-f0-9]{64}$/i', $casti[3])) {
        return 0;
    }

    $idZarizeni = (int)$casti[1];
    $expirace = (int)$casti[2];
    if ($idZarizeni <= 0 || $expirace < time()) {
        return 0;
    }

    $endpointHash = cb_duveryhodne_zarizeni_endpoint_hash($db, $idUser, $idZarizeni);
    if ($endpointHash === '') {
        return 0;
    }

    $ocekavanyPodpis = cb_duveryhodne_zarizeni_podpis($idUser, $idZarizeni, $expirace, $endpointHash);
    if ($ocekavanyPodpis === '' || !hash_equals($ocekavanyPodpis, strtolower($casti[3]))) {
        return 0;
    }

    return $idZarizeni;
}
