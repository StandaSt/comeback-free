<?php
declare(strict_types=1);

/* Ucel: Oddeluje pravo cist a planovat; planovani navic vyzaduje provozni funkci a vlastni MAIN mnozinu. */
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

// Ani individualne pravo nebo role vedeni neobchazi MAIN a provozni funkci planovace.
function cb_smeny_planovani_pobocky(mysqli $db): array
{
    if (!cb_smeny_planovani_vidi()) return [];
    $person=cb_smeny_planovani_rozsah_osoba($db);
    $function=cb_hr_funkce_osoby($db,$person);
    if (($function['zarazeni']??'')!=='provozni' || cb_hr_zarazeni_chyby($db,$person)!==[]) return [];
    return array_intersect_key(cb_smeny_sablony_pobocky($db),array_flip(cb_hr_main_ids($db,$person)));
}

// Volano po zamceni osoby i v zapisove akci; soubezne HR nesmi zmenit opravneni uprostred zapisu.
function cb_smeny_planovani_vyzaduj_pobocku(mysqli $db, int $idPob): void
{
    if (!cb_smeny_planovani_edituje() || !isset(cb_smeny_planovani_pobocky($db)[$idPob])) {
        throw new CbUserVisibleException('Plánování vyžaduje provozní funkci, právo plánovat a tuto pobočku mezi vlastními MAIN.');
    }
}

