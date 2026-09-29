<?php
// Zapisuje technickou chybu do log_chyby a podle zadání předá konkrétní adminskou push notifikaci.
declare(strict_types=1);

/*
 * ČISTÝ ZÁPIS DO DB: log_chyby
 *
 * Tento soubor:
 * - nic nerozhoduje
 * - nic nehádá
 * - jen zapíše do DB to, co dostane
 */

if (!function_exists('db_zapis_log_chyby')) {

    function db_zapis_log_chyby(
        mysqli $conn,
        ?int $idUser,
        string $modul,
        string $akce,
        string $kod,
        string $zprava,
        ?string $detail,
        ?string $soubor,
        ?int $radek,
        ?string $url,
        ?string $dataJson,
        int $vyreseno = 0,
        ?string $poznamka = null,
        bool $sendPush = true,
        ?string $pushTitle = null,
        string $pushType = 'SYSTEM_ERROR_ADMIN'
    ): bool {

        $stmt = $conn->prepare(
            'INSERT INTO log_chyby
                (id_user, modul, akce, kod, zprava, detail, soubor, radek, url, data_json, vyreseno, poznamka)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (log_chyby insert).');
        }

        $stmt->bind_param(
            'issssssissis',
            $idUser,
            $modul,
            $akce,
            $kod,
            $zprava,
            $detail,
            $soubor,
            $radek,
            $url,
            $dataJson,
            $vyreseno,
            $poznamka
        );

        $stmt->execute();
        $stmt->close();

        if (!$sendPush) {
            return true;
        }

        try {
            require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
            return cb_push_send_error_admin(
                $zprava,
                $soubor,
                $radek,
                1,
                $pushTitle ?? 'Chyba IS',
                $pushType
            );
        } catch (Throwable $e) {
            error_log('[cb_error_push_failed] ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }
    }

} else {

    // !!! KRITICKÁ CHYBA !!!
    // Funkce db_zapis_log_chyby už existuje, což se nemá stát

    // nutno doplnit signalizaci (push)

    try {
        $conn = db();

        $stmt = $conn->prepare(
            'INSERT INTO log_chyby
                (id_user, modul, akce, kod, zprava, detail, soubor, radek, url, data_json, vyreseno, poznamka)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        if ($stmt) {
            $idUser = null;
            $modul = 'SYSTEM';
            $akce = 'INIT';
            $kod = 'DUPLICATE_FUNCTION';
            $zprava = 'Funkce db_zapis_log_chyby již existuje.';
            $detail = 'Soubor byl načten vícekrát nebo došlo k chybě v architektuře.';
            $soubor = __FILE__;
            $radek = __LINE__;
            $url = $_SERVER['REQUEST_URI'] ?? null;
            $dataJson = null;
            $vyreseno = 0;
            $poznamka = null;

            $stmt->bind_param(
                'issssssissis',
                $idUser,
                $modul,
                $akce,
                $kod,
                $zprava,
                $detail,
                $soubor,
                $radek,
                $url,
                $dataJson,
                $vyreseno,
                $poznamka
            );

            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // ani fallback nesmí shodit aplikaci
    }
}

/* db/zapis_log_chyby.php * Verze: V2 * Aktualizace: 24.03.2026 */
// Konec souboru
