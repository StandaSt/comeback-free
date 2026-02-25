<?php
// db/db_user_set_pobocka.php * Verze: V1 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * NASTAVENÍ POBOČEK UŽIVATELE (aktuální stav ze Směn)
 *
 * Účel:
 * - po přihlášení zajistit, že user_pobocka odpovídá aktuálnímu stavu ze Směn
 * - historii teď neřešíme: nastavujeme "teď platí"
 *
 * Vstup:
 * - $workingBranchCodes = seznam kódů poboček ze Směn (workingBranchNames)
 *
 * Chování:
 * 1) normalizace kódů (trim, prázdné pryč)
 * 2) kódy -> id_pob přes pobocka.kod
 * 3) smaže všechny vazby user_pobocka pro uživatele
 * 4) vloží aktuální vazby (id_user, id_pob)
 *
 * Důležité:
 * - soubor NEVOLÁ Směny (API)
 * - používá pobocka.kod (ne název)
 */

if (!function_exists('cb_db_set_user_pobocka')) {

    /**
     * Nastaví pobočky uživatele podle aktuálního seznamu ze Směn.
     *
     * Poznámka:
     * - pokud Směny vrátí prázdný seznam, uživatel skončí bez poboček (a to je záměr).
     */
    function cb_db_set_user_pobocka(mysqli $conn, int $idUser, array $workingBranchCodes): void
    {
        // 1) normalizace vstupu (kódy poboček)
        $codes = [];
        foreach ($workingBranchCodes as $c) {
            $c = trim((string)$c);
            if ($c !== '') {
                $codes[] = $c;
            }
        }

        // 2) kódy -> id_pob
        $ids = [];
        if ($codes) {
            $stmtFind = $conn->prepare('SELECT id_pob FROM pobocka WHERE kod=? LIMIT 1');
            if ($stmtFind === false) {
                throw new RuntimeException('DB: prepare selhal (pobocka lookup).');
            }

            foreach ($codes as $kod) {
                $stmtFind->bind_param('s', $kod);
                $stmtFind->execute();
                $stmtFind->bind_result($idPobDb);
                if ($stmtFind->fetch()) {
                    $idPob = (int)$idPobDb;
                    if ($idPob > 0) {
                        // deduplikace přes klíč
                        $ids[$idPob] = true;
                    }
                }
                $stmtFind->free_result();
            }
            $stmtFind->close();
        }

        $desiredIds = array_keys($ids);
        sort($desiredIds);

        // 3) smaž aktuální vazby (nastavujeme nový stav)
        $stmtDelAll = $conn->prepare('DELETE FROM user_pobocka WHERE id_user=?');
        if ($stmtDelAll === false) {
            throw new RuntimeException('DB: prepare selhal (user_pobocka delete all).');
        }
        $stmtDelAll->bind_param('i', $idUser);
        $stmtDelAll->execute();
        $stmtDelAll->close();

        // 4) vlož aktuální vazby
        if ($desiredIds) {
            $stmtIns = $conn->prepare('INSERT INTO user_pobocka (id_user, id_pob) VALUES (?,?)');
            if ($stmtIns === false) {
                throw new RuntimeException('DB: prepare selhal (user_pobocka insert).');
            }

            foreach ($desiredIds as $idPob) {
                $stmtIns->bind_param('ii', $idUser, $idPob);
                $stmtIns->execute();
            }
            $stmtIns->close();
        }
    }
}

// db/db_user_set_pobocka.php * Verze: V1 * Aktualizace: 21.2.2026 * Počet řádků: 98
// Konec souboru