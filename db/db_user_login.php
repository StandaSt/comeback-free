<?php
// db/db_user_login.php * Verze: V4 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * DB SYNC PO PŘIHLÁŠENÍ (Směny -> Comeback DB)
 *
 * Tenhle soubor je „orchestr“:
 * - bere data ze session (už je naplnil login_smeny.php)
 * - volá malé funkce pro DB (user, pobocka, povoleni, role, slot)
 * - vše v transakci
 *
 * Důležité:
 * - NESMÍ volat Směny (API)
 * - při chybě vyhodí výjimku (login_smeny.php to chytí a přihlášení zruší)
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/login_diagnostika.php';
require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/db_pobocka.php';
require_once __DIR__ . '/db_povoleni.php';
require_once __DIR__ . '/db_user_role.php';
require_once __DIR__ . '/db_user_slot.php';

if (!function_exists('cb_db_user_login')) {

    /**
     * Hlavní funkce – volá se z login_smeny.php po úspěšném loginu do Směn.
     */
    function cb_db_user_login(): void
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

        // workingBranchNames = seznam kódů poboček (stringy)
        $working = $branches['workingBranchNames'] ?? [];
        if (!is_array($working)) {
            $working = [];
        }

        // normalizace: string + bez prázdných položek
        $workingCodes = [];
        foreach ($working as $code) {
            $code = trim((string)$code);
            if ($code !== '') {
                $workingCodes[] = $code;
            }
        }

        $conn = db();
        $conn->begin_transaction();

        try {
            cb_login_log_line('db_sync_start', [
                'id_user' => (string)$idUser,
                'branches' => (string)count($workingCodes),
            ]);

            // A) user (profil)
            cb_db_upsert_user($conn, $profile);

            // B) pobocka (zajistit kódy)
            $desiredPobIds = cb_db_ensure_branches_get_ids($conn, $workingCodes);

            // C) povolení (aktuální + historie)
            $permChanges = cb_db_sync_permissions($conn, $idUser, $desiredPobIds);

            // D) role (aktuální) – podle Směn (data už jsou v $profile ze session)
            $roleChanges = db_user_role_sync($conn, $idUser, $profile);

            // E) sloty (aktuální) – podle Směn (data už jsou v $profile ze session)
            $slotChanges = db_user_slot_sync($conn, $idUser, $profile);

            // F) login event (akce=1)
            cb_db_insert_login_event($conn, $idUser, 1);

            $conn->commit();

            cb_login_log_line('db_sync_ok', [
                'id_user' => (string)$idUser,

                // povolení poboček
                'pob_add' => (string)($permChanges['add'] ?? 0),
                'pob_del' => (string)($permChanges['del'] ?? 0),

                // role
                'role_add' => (string)($roleChanges['add'] ?? 0),
                'role_del' => (string)($roleChanges['del'] ?? 0),

                // sloty
                'slot_add' => (string)($slotChanges['add'] ?? 0),
                'slot_del' => (string)($slotChanges['del'] ?? 0),
            ]);

        } catch (Throwable $e) {
            $conn->rollback();
            cb_login_log_line('db_sync_fail', ['id_user' => (string)$idUser], $e);
            throw $e;
        }
    }
}

/* db/db_user_login.php * Verze: V4 * Aktualizace: 12.2.2026 * Počet řádků: 114 */
// Konec souboru