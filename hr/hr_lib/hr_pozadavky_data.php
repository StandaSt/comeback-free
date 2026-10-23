<?php
/*
 * Ucel souboru: Pripravuje data a opravneni pro PP stranku HR Pozadavky.
 * Nevykresluje HTML ani nezpracovava formularove akce.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../common/db/db_cis_slot.php';

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

    $mainPobocky=$idUser>0?hr_main_pobocky_uzivatele($db,$idUser):[];
    if (count($mainPobocky)===1) $mainPobocka=$mainPobocky[0];
    else $mainPobocka=['id_pob'=>0,'nazev'=>implode(', ',array_column($mainPobocky,'nazev'))];
    $muzeCist=$muzeCistVse || ($muzeCistMain && $mainPobocky!==[]);
    $nove = [];
    $vyresene = [];
    $expirovane = [];
    $zrusene = [];
    $sloty = cb_cis_slot_nazvy($db, true);
    unset($sloty[0]);

    if ($muzeCistVse) {
        $nove = hr_nacti_pozadavky_podle_stavu($db, 1);
        $vyresene = hr_nacti_pozadavky_podle_stavu($db, 2);
        $expirovane = hr_nacti_pozadavky_podle_stavu($db, 3);
        $zrusene = hr_nacti_pozadavky_podle_stavu($db, 4);
    } elseif ($muzeCist) {
        foreach ($mainPobocky as $branch) {
            $idPob=(int)$branch['id_pob'];
            $nove=array_merge($nove,hr_nacti_nove_pozadavky_pobocky($db,$idPob));
            $vyresene=array_merge($vyresene,hr_nacti_pozadavky_pobocky_podle_stavu($db,$idPob,2));
            $expirovane=array_merge($expirovane,hr_nacti_pozadavky_pobocky_podle_stavu($db,$idPob,3));
            $zrusene=array_merge($zrusene,hr_nacti_pozadavky_pobocky_podle_stavu($db,$idPob,4));
        }
    }

    return [
        'pozadavkyMuzeCist' => $muzeCist,
        'pozadavkyMuzeZadat' => $muzeZadat && $mainPobocky!==[] && $idUser>0,
        'pozadavkyMuzeZrusit' => $muzeZrusit && $idUser > 0,
        'pozadavkyZobraziPobocku' => $muzeCistVse || count($mainPobocky)>1,
        'pozadavkyMainPobocka' => $mainPobocka, 'pozadavkyMainPobocky'=>$mainPobocky,
        'pozadavkyUserId' => $idUser,
        'pozadavkyChybaZadani' => $chybaZadani,
        'pozadavkyRozsah' => $muzeCistVse ? 'všech poboček' : 'pobočky ' . (string)($mainPobocka['nazev'] ?? ''),
        'pozadavkyNove' => $nove,
        'pozadavkyVyresene' => $vyresene,
        'pozadavkyExpirovane' => $expirovane,
        'pozadavkyZrusene' => $zrusene,
        'pozadavkySloty' => $sloty,
    ];
}
