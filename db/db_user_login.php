<?php
// db/db_user_login.php * Verze: V13 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * DB SYNC PO PŘIHLÁŠENÍ (Směny -> Comeback DB)
 *
 * Tenhle soubor je „orchestr“:
 * - vezme připravené vstupy (ze session přes funkci v /funkce)
 * - provede DB synchronizaci (user, pobočky, role, sloty)
 * - zapíše login event
 * - zapíše log volání Směn (api_smeny) – buffer se flushne až po úspěšném loginu
 * - vše probíhá v jedné transakci (kromě session timeout)
 *
 * Důležité:
 * - NESMÍ volat Směny (API)
 * - při chybě vyhodí výjimku (login_smeny.php to chytí a přihlášení zruší)
 *
 * Efektivní role:
 * - nastavuje se uvnitř db/db_user_role.php (varianta A)
 *
 * 1 úspěšný login:
 * - 1 řádek v user_login (akce=1)
 * - 1 řádek v user_spy
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/login_diagnostika.php';

require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/db_user_role.php';
require_once __DIR__ . '/db_user_slot.php';

require_once __DIR__ . '/db_login_zapis.php';
require_once __DIR__ . '/db_login_blok_info.php';
require_once __DIR__ . '/db_user_set_pobocka.php';

require_once __DIR__ . '/db_api_smeny.php';

require_once __DIR__ . '/../funkce/fce_session_timeout.php';
require_once __DIR__ . '/../funkce/fce_login_vstupy.php';

if (!function_exists('cb_db_user_login')) {

    /**
     * Hlavní DB orchestr – volá se po úspěšném loginu do Směn.
     */
    function cb_db_user_login(): void
    {
        // 1) připrav vstupy (ze session, bez DB)
        $data = fce_login_vstupy_priprav();

        $idUser = (int)$data['id_user'];
        $profile = $data['profile'];
        $working = $data['working'];

        $conn = db();
        $conn->begin_transaction();

        try {
            cb_login_log_line('db_sync_start', [
                'id_user' => (string)$idUser,
                'branches' => (string)count($working),
            ]);

            // A) user (profil)
            cb_db_upsert_user($conn, $profile);

            // B) pobočky uživatele (nastav aktuální stav)
            cb_db_set_user_pobocka($conn, $idUser, $working);

            // C) role (synchronizace + nastavení efektivní role)
            $roleChanges = db_user_role_sync($conn, $idUser, $profile);

            // D) sloty (aktuální)
            $slotChanges = db_user_slot_sync($conn, $idUser, $profile);

            // E) login event (akce=1) + user_spy
            $idLogin = cb_db_insert_login_and_spy($conn, $idUser);

            // F) login info do session (pro hlavičku)
            cb_db_fill_login_info_session($conn, $idUser, $idLogin);

            // F2) API log Směn (api_smeny) – zapíšeme až teď, kdy už známe id_user i id_login
            try {
                db_api_smeny_flush($conn, $idUser, $idLogin);
            } catch (Throwable $eLog) {
                error_log('api_smeny flush selhal: ' . $eLog->getMessage());
            }

            $conn->commit();

            // G) timeout session (nemá DB, jen session)
            cb_session_init_timeout();

            cb_login_log_line('db_sync_ok', [
                'id_user' => (string)$idUser,
                'id_login' => (string)$idLogin,

                'role_add' => (string)($roleChanges['add'] ?? 0),
                'role_del' => (string)($roleChanges['del'] ?? 0),

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

// db/db_user_login.php * Verze: V13 * Aktualizace: 21.2.2026
// Počet řádků: 109
// Konec souboru