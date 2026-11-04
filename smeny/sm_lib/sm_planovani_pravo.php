<?php
declare(strict_types=1);

/* Účel: Odděluje právo číst a plánovat; manažerům vymezuje pobočky místem výkonu práce včetně oblastí, provozním pracovníkům vlastní množinou MAIN. */
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/** Přehled ostatních lidí vyžaduje čtecí oprávnění. */
function cb_smeny_planovani_vidi(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(403); }
/** Právo 407 řídí změny a dokončení pracovního rozpisu. */
function cb_smeny_planovani_edituje(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(407); }
/** Zveřejnění je dokončení plánování, nikoli další samostatné schválení. */
function cb_smeny_planovani_publikuje(): bool { return cb_smeny_planovani_edituje(); }

// Prevzata prava testuji rozsah zdrojove osoby, autor zapisu zustava skutecnym prihlasenym uzivatelem.
function cb_smeny_planovani_rozsah_osoba(mysqli $db): int
{
    $profile=cb_prebrani_prav_profil();
    if ($profile===null) return cb_smeny_sablony_id_person($db);
    $idUser=(int)$profile['id_user_zdroj'];
    $stmt=$db->prepare('SELECT id_person FROM hr_person WHERE id_user=? AND aktivni=1');
    $stmt->bind_param('i',$idUser); $stmt->execute();
    $id=(int)($stmt->get_result()->fetch_assoc()['id_person']??0); $stmt->close(); return $id;
}

// Manažer nemá MAIN; jeho rozsah určují platná místa výkonu práce včetně členství poboček v oblastech.
// Průnik se společným přístupem zachová také omezení firmy a případného přebrání práv.
function cb_smeny_planovani_pobocky(mysqli $db): array
{
    $person=cb_smeny_planovani_rozsah_osoba($db);
    // Manažerský rozsah je nezávislý na 403; ostatní bez tohoto práva končí jako dosud.
    $managerIds=cb_smeny_manazer_pobocky_ids($db,$person);
    if ($managerIds===null && !cb_smeny_planovani_vidi()) return [];
    $function=cb_hr_funkce_osoby($db,$person);
    $zarazeni=$function['zarazeni']??'';
    if (!in_array($zarazeni,['provozni','manazerske'],true) || cb_hr_zarazeni_chyby($db,$person)!==[]) return [];
    if ($zarazeni==='manazerske') {
        return cb_smeny_sablony_pobocky($db);
    }
    $ids=cb_hr_main_ids($db,$person);
    return array_intersect_key(cb_smeny_sablony_pobocky($db),array_flip($ids));
}

// Volano po zamceni osoby i v zapisove akci; soubezne HR nesmi zmenit opravneni uprostred zapisu.
function cb_smeny_planovani_vyzaduj_pobocku(mysqli $db, int $idPob): void
{
    if (!cb_smeny_planovani_edituje() || !isset(cb_smeny_planovani_pobocky($db)[$idPob])) {
        throw new CbUserVisibleException('Plánování vyžaduje právo editovat plán směn a pobočku v povoleném rozsahu: u manažera podle místa výkonu práce, u provozního pracovníka podle MAIN.');
    }
}

