<?php
// funkce/fce_login_vstupy.php * Verze: V1 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * PŘÍPRAVA VSTUPŮ PRO DB LOGIN ORCHESTR (bez DB)
 *
 * Účel:
 * - vytáhne ze session data, která předtím naplnil login_smeny.php
 * - provede stejné kontroly jako dřív v db/db_user_login.php
 * - připraví hodnoty pro DB synchronizaci (profil + pobočky)
 *
 * Co to NEDĚLÁ:
 * - nesahá do DB
 * - nevolá Směny (API)
 *
 * Proč to existuje:
 * - zkrácení a zpřehlednění db/db_user_login.php
 * - DB orchestr pak řeší už jen transakci a DB kroky
 *
 * Vstupy ve session:
 * - $_SESSION['cb_user_profile']  ... profil ze Směn (musí obsahovat 'id')
 * - $_SESSION['cb_user_branches'] ... pobočky ze Směn (musí být pole)
 *   - očekává se klíč 'workingBranchNames' (seznam kódů poboček)
 *
 * Návrat:
 * - pole:
 *   - 'id_user'  (int)
 *   - 'profile'  (array)
 *   - 'working'  (array)  seznam kódů poboček (může být prázdný)
 */

if (!function_exists('fce_login_vstupy_priprav')) {

    /**
     * Připraví vstupy ze session pro DB login orchestr.
     *
     * Vrací pole:
     * - id_user  ... (int) id uživatele ze Směn
     * - profile  ... (array) profil uživatele ze Směn
     * - working  ... (array) kódy poboček (workingBranchNames), může být prázdné
     *
     * Vyhazuje výjimku, pokud:
     * - chybí profil nebo nemá id
     * - chybí pobočky (cb_user_branches)
     */
    function fce_login_vstupy_priprav(): array
    {
        $profile = $_SESSION['cb_user_profile'] ?? null;
        $branches = $_SESSION['cb_user_branches'] ?? null;

        if (!is_array($profile) || empty($profile['id'])) {
            throw new RuntimeException('Chybí profil uživatele v session (cb_user_profile).');
        }
        if (!is_array($branches)) {
            throw new RuntimeException('Chybí pobočky uživatele v session (cb_user_branches).');
        }

        $idUser = (int)$profile['id'];

        // workingBranchNames = seznam kódů poboček, na kterých může uživatel pracovat
        $working = $branches['workingBranchNames'] ?? [];
        if (!is_array($working)) {
            $working = [];
        }

        return [
            'id_user' => $idUser,
            'profile' => $profile,
            'working' => $working,
        ];
    }
}

// funkce/fce_login_vstupy.php * Verze: V1 * Aktualizace: 21.2.2026
// Počet řádků: 77
// Konec souboru