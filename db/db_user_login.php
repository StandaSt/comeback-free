<?php
// db/db_user_login.php * Verze: V14 * Aktualizace: 02.04.2026
declare(strict_types=1);

/*
 * DB SYNC PO PĹIHLĂĹ ENĂŤ (SmÄ›ny -> Comeback DB)
 *
 * Tenhle soubor je â€žorchestrâ€ś:
 * - vezme pĹ™ipravenĂ© vstupy (ze session pĹ™es funkci v /funkce)
 * - provede DB synchronizaci (user, poboÄŤky, role, sloty)
 * - zapĂ­Ĺˇe login event
 * - zapĂ­Ĺˇe log volĂˇnĂ­ SmÄ›n (api_smeny) â€“ buffer se flushne aĹľ po ĂşspÄ›ĹˇnĂ©m loginu
 * - vĹˇe probĂ­hĂˇ v jednĂ© transakci (kromÄ› session timeout)
 *
 * DĹŻleĹľitĂ©:
 * - NESMĂŤ volat SmÄ›ny (API)
 * - pĹ™i chybÄ› vyhodĂ­ vĂ˝jimku (login_smeny.php to chytĂ­ a pĹ™ihlĂˇĹˇenĂ­ zruĹˇĂ­)
 *
 * EfektivnĂ­ role:
 * - nastavuje se uvnitĹ™ db/db_user_role.php (varianta A)
 *
 * 1 ĂşspÄ›ĹˇnĂ˝ login:
 * - 1 Ĺ™Ăˇdek v user_login (akce=1)
 * - 1 Ĺ™Ăˇdek v user_spy
 */

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
     * HlavnĂ­ DB orchestr â€“ volĂˇ se po ĂşspÄ›ĹˇnĂ©m loginu do SmÄ›n.
     */
    function cb_db_user_login(): void
    {
        // 1) pĹ™iprav vstupy (ze session, bez DB)
        $data = fce_login_vstupy_priprav();

        $idUser = (int)$data['id_user'];
        $profile = $data['profile'];
        $working = $data['working'];
        $mainBranch = $data['main'] ?? null;

        $conn = db();
        $conn->begin_transaction();

        try {

            // A) user (profil)
            cb_db_upsert_user($conn, $profile, true);

            // A2) user_set (vychozi zaznam po prvnim loginu)
            cb_db_ensure_user_set($conn, $idUser);

            // B) poboÄŤky uĹľivatele (nastav aktuĂˇlnĂ­ stav)
            cb_db_set_user_pobocka($conn, $idUser, $working, is_string($mainBranch) ? $mainBranch : null);

            // C) role (synchronizace + nastavenĂ­ efektivnĂ­ role)
            $roleChanges = db_user_role_sync($conn, $idUser, $profile);

            // D) sloty (aktuĂˇlnĂ­)
            $slotChanges = db_user_slot_sync($conn, $idUser, $profile);

            // E) login event (akce=1) + user_spy
            $idLogin = cb_db_insert_login_and_spy($conn, $idUser);

            // F) login info do session (pro hlaviÄŤku)
            cb_db_fill_login_info_session($conn, $idUser, $idLogin);

            // F2) API log SmÄ›n (api_smeny) â€“ zapĂ­Ĺˇeme aĹľ teÄŹ, kdy uĹľ znĂˇme id_user i id_login
            try {
                db_api_smeny_flush($conn, $idUser, $idLogin);
            } catch (Throwable $eLog) {
                error_log('api_smeny flush selhal: ' . $eLog->getMessage());
            }

            $conn->commit();

            // G) timeout session (nemĂˇ DB, jen session)
            cb_session_init_timeout();


        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

// db/db_user_login.php * Verze: V14 * Aktualizace: 02.04.2026
// PoÄŤet Ĺ™ĂˇdkĹŻ: 117
// Konec souboru
