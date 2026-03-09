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

if (!function_exists('cb_karty_get_by_kod')) {
    /**
     * Nacte 1 aktivni kartu podle kodu.
     */
    function cb_karty_get_by_kod(mysqli $conn, string $kod): ?array
    {
        $stmt = $conn->prepare('
            SELECT id_karta, kod, nazev, soubor, min_role, poradi, aktivni
            FROM karty
            WHERE kod=? AND aktivni=1
            LIMIT 1
        ');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $kod);
        $stmt->execute();
        $stmt->bind_result($idKarta, $kodDb, $nazev, $soubor, $minRole, $poradi, $aktivni);

        $row = null;
        if ($stmt->fetch()) {
            $row = [
                'id_karta' => (int)$idKarta,
                'kod' => (string)$kodDb,
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
     * Vyhodnoti, zda uzivatel muze videt kartu podle kodu.
     */
    function cb_karty_user_can_view(mysqli $conn, int $idUser, int $idRole, string $kodKarty): bool
    {
        $karta = cb_karty_get_by_kod($conn, $kodKarty);
        if (!is_array($karta)) {
            return false;
        }

        $idKarta = (int)$karta['id_karta'];
        $minRole = (int)$karta['min_role'];

        $override = cb_karty_get_user_override($conn, $idUser, $idKarta);
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
            SELECT k.id_karta, k.kod, k.nazev, k.soubor, k.min_role, k.poradi, kv.akce
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
        $stmt->bind_result($idKarta, $kod, $nazev, $soubor, $minRole, $poradi, $akce);

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
                    'kod' => (string)$kod,
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
