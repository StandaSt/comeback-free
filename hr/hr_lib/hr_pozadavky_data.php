<?php
/*
 * Ucel souboru: Pripravuje data a opravneni pro PP stranku HR Pozadavky.
 * Nevykresluje HTML ani nezpracovava formularove akce.
 */
declare(strict_types=1);

/*
 * Vraci data pozadavku podle prav prihlaseneho uzivatele.
 */
function hr_pozadavky_data(mysqli $db): array
{
    $user = $_SESSION['cb_user'] ?? [];
    $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
    $muzeCistVse = cb_pravo_ma(311);
    $muzeCistMain = cb_pravo_ma(313);
    $muzeZadat = cb_pravo_ma(312);
    $muzeZrusit = cb_pravo_ma(314);
    $mainPobocka = [];
    $chybaZadani = '';

    if ($idUser > 0 && ($muzeCistMain || $muzeZadat)) {
        try {
            $mainPobocka = hr_nacti_hlavni_pobocku_uzivatele($db, $idUser);
        } catch (RuntimeException $e) {
            $chybaZadani = $e->getMessage();
        }
    }

    $muzeCist = $muzeCistVse || ($muzeCistMain && (int)($mainPobocka['id_pob'] ?? 0) > 0);
    $nove = [];
    $vyresene = [];
    $expirovane = [];
    $zrusene = [];

    if ($muzeCistVse) {
        $nove = hr_nacti_pozadavky_podle_stavu($db, 1);
        $vyresene = hr_nacti_pozadavky_podle_stavu($db, 2);
        $expirovane = hr_nacti_pozadavky_podle_stavu($db, 3);
        $zrusene = hr_nacti_pozadavky_podle_stavu($db, 4);
    } elseif ($muzeCist) {
        $idPob = (int)$mainPobocka['id_pob'];
        $nove = hr_nacti_nove_pozadavky_pobocky($db, $idPob);
        $vyresene = hr_nacti_pozadavky_pobocky_podle_stavu($db, $idPob, 2);
        $expirovane = hr_nacti_pozadavky_pobocky_podle_stavu($db, $idPob, 3);
        $zrusene = hr_nacti_pozadavky_pobocky_podle_stavu($db, $idPob, 4);
    }

    return [
        'pozadavkyMuzeCist' => $muzeCist,
        'pozadavkyMuzeZadat' => $muzeZadat && (int)($mainPobocka['id_pob'] ?? 0) > 0 && $idUser > 0,
        'pozadavkyMuzeZrusit' => $muzeZrusit && $idUser > 0,
        'pozadavkyZobraziPobocku' => $muzeCistVse,
        'pozadavkyMainPobocka' => $mainPobocka,
        'pozadavkyUserId' => $idUser,
        'pozadavkyChybaZadani' => $chybaZadani,
        'pozadavkyRozsah' => $muzeCistVse ? 'všech poboček' : 'pobočky ' . (string)($mainPobocka['nazev'] ?? ''),
        'pozadavkyNove' => $nove,
        'pozadavkyVyresene' => $vyresene,
        'pozadavkyExpirovane' => $expirovane,
        'pozadavkyZrusene' => $zrusene,
    ];
}
