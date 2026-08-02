<?php
// db/db_karty_prava.php * Verze: V1 * Aktualizace: 07.03.2026
declare(strict_types=1);

/*
 * KARTY + OPRAVNENI
 *
 * Ucel:
 * - cist definice karet z tabulky `karty`
 * - vyhodnotit pravo uzivatele na kartu pres:
 *   1) user vyjimku (deny/allow)
 *   2) role pravidlo (min_role)
 *
 * Pravidla:
 * - karta je viditelna jen kdyz je aktivni=1
 * - priorita opravneni:
 *   deny (vyjimka) > allow (vyjimka) > role (min_role)
 */

if (!function_exists('cb_karty_get_by_id')) {
    /**
     * Nacte 1 aktivni kartu podle ID.
     */
    function cb_karty_get_by_id(mysqli $conn, int $idKarta): ?array
    {
        $stmt = $conn->prepare('
            SELECT id_karta, nazev, soubor, min_role, poradi, aktivni
            FROM karty
            WHERE id_karta=? AND aktivni=1
            LIMIT 1
        ');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $idKarta);
        $stmt->execute();
        $stmt->bind_result($idKartaDb, $nazev, $soubor, $minRole, $poradi, $aktivni);

        $row = null;
        if ($stmt->fetch()) {
            $row = [
                'id_karta' => (int)$idKartaDb,
                'nazev' => (string)$nazev,
                'soubor' => (string)$soubor,
                'min_role' => (int)$minRole,
                'poradi' => (int)$poradi,
                'aktivni' => (int)$aktivni,
            ];
        }
        $stmt->close();

        return $row;
    }
}

if (!function_exists('cb_karty_get_by_kod')) {
    /**
     * Zpetna kompatibilita: po zruseni sloupce `kod` umi pracovat jen s ciselny hodnotou.
     */
    function cb_karty_get_by_kod(mysqli $conn, string $kod): ?array
    {
        $id = (int)$kod;
        if ($id <= 0) {
            return null;
        }
        return cb_karty_get_by_id($conn, $id);
    }
}

if (!function_exists('cb_karty_get_user_override')) {
    /**
     * Vrati aktivni user vyjimku pro kartu: allow/deny nebo null.
     */
    function cb_karty_get_user_override(mysqli $conn, int $idUser, int $idKarta): ?string
    {
        $stmt = $conn->prepare('
            SELECT akce
            FROM karty_vyjimky
            WHERE id_user=? AND id_karta=? AND aktivni=1
            LIMIT 1
        ');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ii', $idUser, $idKarta);
        $stmt->execute();
        $stmt->bind_result($akce);

        $out = null;
        if ($stmt->fetch()) {
            $v = (string)$akce;
            if ($v === 'allow' || $v === 'deny') {
                $out = $v;
            }
        }
        $stmt->close();

        return $out;
    }
}

if (!function_exists('cb_karty_user_can_view')) {
    /**
     * Vyhodnoti, zda uzivatel muze videt kartu podle ID.
     */
    function cb_karty_user_can_view(mysqli $conn, int $idUser, int $idRole, int $idKarta): bool
    {
        $karta = cb_karty_get_by_id($conn, $idKarta);
        if (!is_array($karta)) {
            return false;
        }

        $idKartaDb = (int)$karta['id_karta'];
        $minRole = (int)$karta['min_role'];

        $override = cb_karty_get_user_override($conn, $idUser, $idKartaDb);
        if ($override === 'deny') {
            return false;
        }
        if ($override === 'allow') {
            return true;
        }

        // Bez vyjimky rozhoduje role (novy model: 1 = nejvyssi opravneni).
        return $idRole <= $minRole;
    }
}

if (!function_exists('cb_karty_load_visible_for_user')) {
    /**
     * Nacte seznam aktivnich karet viditelnych pro uzivatele.
     * Vysledek je serazen podle poradi, pak id_karta.
     */
    function cb_karty_load_visible_for_user(mysqli $conn, int $idUser, int $idRole): array
    {
        $out = [];

        $stmt = $conn->prepare('
            SELECT k.id_karta, k.nazev, k.soubor, k.min_role, k.poradi, kv.akce
            FROM karty k
            LEFT JOIN karty_vyjimky kv
              ON kv.id_karta = k.id_karta
             AND kv.id_user = ?
             AND kv.aktivni = 1
            WHERE k.aktivni = 1
            ORDER BY k.poradi ASC, k.id_karta ASC
        ');
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($idKarta, $nazev, $soubor, $minRole, $poradi, $akce);

        while ($stmt->fetch()) {
            $v = is_string($akce) ? $akce : null;

            // Priorita: deny > allow > role.
            $can = false;
            if ($v === 'deny') {
                $can = false;
            } elseif ($v === 'allow') {
                $can = true;
            } else {
                $can = ($idRole <= (int)$minRole);
            }

            if ($can) {
                $out[] = [
                    'id_karta' => (int)$idKarta,
                    'nazev' => (string)$nazev,
                    'soubor' => (string)$soubor,
                    'min_role' => (int)$minRole,
                    'poradi' => (int)$poradi,
                    'override' => $v,
                ];
            }
        }

        $stmt->close();
        return $out;
    }
}

// db/db_karty_prava.php * Verze: V1 * Aktualizace: 07.03.2026 * Pocet radku: 184
// Konec souboru
