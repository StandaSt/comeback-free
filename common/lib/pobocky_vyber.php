<?php
/*
 * Ucel souboru: Spolecna pravidla pro povolene a vybrane pobocky uzivatele.
 * Poskytuje data pro komponenty a udrzuje globalni vyber pobocek v session.
 */
declare(strict_types=1);

if (!function_exists('cb_pobocky_sanitize_ids')) {
    /**
     * Normalizuje libovolne hodnoty na serazeny seznam kladnych ID pobocek.
     *
     * @param mixed $raw
     * @return int[]
     */
    function cb_pobocky_sanitize_ids(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $uniq = [];
        foreach ($raw as $v) {
            $id = (int)$v;
            if ($id > 0) {
                $uniq[$id] = true;
            }
        }

        $ids = array_keys($uniq);
        sort($ids);
        return $ids;
    }
}

if (!function_exists('cb_pobocky_set_selected')) {
    /**
     * Ulozi globalni vyber pobocek do session.
     *
     * @param int[] $ids
     */
    function cb_pobocky_set_selected(array $ids): void
    {
        $clean = cb_pobocky_sanitize_ids($ids);
        $_SESSION['selected_pobocky'] = $clean;

        if ($clean) {
            // Kompatibilita se starym kodem.
            $_SESSION['cb_pobocka_id'] = (int)$clean[0];
        }
    }
}

if (!function_exists('cb_pobocky_set_mode')) {
    /**
     * Ulozi zpusob globalniho vyberu pobocek do session.
     */
    function cb_pobocky_set_mode(string $mode, ?string $oblast = null): void
    {
        $mode = trim($mode);
        if (!in_array($mode, ['single', 'area', 'custom', 'auto'], true)) {
            $mode = 'single';
        }
        $_SESSION['selected_pobocky_mode'] = $mode;

        $oblast = ($oblast === null) ? '' : trim($oblast);
        if ($mode === 'area' && $oblast !== '') {
            $_SESSION['selected_oblast'] = $oblast;
        } else {
            $_SESSION['selected_oblast'] = '';
        }

        if ($mode !== 'area') {
            $_SESSION['selected_oblasti'] = [];
        }
    }
}

if (!function_exists('cb_pobocky_get_allowed_rows_for_user')) {
    /**
     * Nacte povolene pobocky uzivatele vcetne nazvu a oblasti pro zobrazeni.
     *
     * @return array<int,array{id_pob:int,nazev:string,oblast:string}>
     */
    function cb_pobocky_get_allowed_rows_for_user(int $idUser): array
    {
        if ($idUser <= 0) {
            return [];
        }

        $conn = db();
        $stmt = $conn->prepare('
            SELECT p.id_pob, p.nazev, p.oblast
            FROM user_pobocka up
            INNER JOIN pobocka p ON p.id_pob = up.id_pob
            WHERE up.id_user = ?
            ORDER BY p.nazev ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nepodarilo se pripravit dotaz na povolene pobocky uzivatele.');
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id_pob'] ?? 0);
                $nazev = trim((string)($row['nazev'] ?? ''));
                if ($id <= 0 || $nazev === '') {
                    continue;
                }
                $oblast = trim((string)($row['oblast'] ?? ''));
                $rows[] = [
                    'id_pob' => $id,
                    'nazev' => $nazev,
                    'oblast' => $oblast !== '' ? $oblast : 'Nezarazeno',
                ];
            }
            $res->close();
        }
        $stmt->close();

        return $rows;
    }
}
if (!function_exists('cb_pobocky_get_allowed_for_user')) {
    /**
     * Vrati ID a oblasti povolenych pobocek pro kontrolu vyberu.
     *
     * @return array{ids:int[], oblasti:array<string,int[]>}
     */
    function cb_pobocky_get_allowed_for_user(int $idUser): array
    {
        if ($idUser <= 0) {
            return ['ids' => [], 'oblasti' => []];
        }

        $idsMap = [];
        $oblastiMap = [];
        foreach (cb_pobocky_get_allowed_rows_for_user($idUser) as $row) {
            $id = (int)$row['id_pob'];
            $idsMap[$id] = true;
            $oblast = (string)$row['oblast'];
            if (!isset($oblastiMap[$oblast])) {
                $oblastiMap[$oblast] = [];
            }
            $oblastiMap[$oblast][$id] = true;
        }

        $ids = array_keys($idsMap);
        sort($ids);

        $oblasti = [];
        foreach ($oblastiMap as $oblast => $idSet) {
            $tmp = array_keys($idSet);
            sort($tmp);
            $oblasti[$oblast] = $tmp;
        }
        ksort($oblasti);

        return [
            'ids' => $ids,
            'oblasti' => $oblasti,
        ];
    }
}

if (!function_exists('get_selected_pobocky')) {
    /**
     * Vrati aktualni globalni vyber pobocek ze session.
     *
     * @return int[]
     */
    function get_selected_pobocky(): array
    {
        $clean = cb_pobocky_sanitize_ids($_SESSION['selected_pobocky'] ?? []);
        if ($clean) {
            return $clean;
        }

        $legacyId = (int)($_SESSION['cb_pobocka_id'] ?? 0);
        if ($legacyId > 0) {
            return [$legacyId];
        }

        return [];
    }
}

if (!function_exists('cb_pobocky_load_selected_from_db')) {
    /**
     * Nacte drive ulozeny globalni vyber pobocek uzivatele.
     *
     * @return int[]
     */
    function cb_pobocky_load_selected_from_db(int $idUser): array
    {
        if ($idUser <= 0) {
            return [];
        }

        $conn = db();
        $stmt = $conn->prepare('SELECT id_pob FROM user_pobocka_set WHERE id_user = ? ORDER BY id_pob ASC');
        if ($stmt === false) {
            throw new RuntimeException('Nepodarilo se pripravit dotaz na user_pobocka_set.');
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();

        $ids = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)($row['id_pob'] ?? 0);
            }
            $res->close();
        }
        $stmt->close();

        return cb_pobocky_sanitize_ids($ids);
    }
}

if (!function_exists('cb_pobocky_bootstrap_session')) {
    /**
     * Udrzi session stav vyberu pobocek konzistentni.
     */
    function cb_pobocky_bootstrap_session(): void
    {
        $selected = get_selected_pobocky();
        if ($selected) {
            cb_pobocky_set_selected($selected);
            return;
        }

        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
        if ($idUser <= 0) {
            return;
        }

        try {
            $allowed = cb_pobocky_get_allowed_for_user($idUser);
            $allowedIds = cb_pobocky_sanitize_ids($allowed['ids'] ?? []);
            if (!$allowedIds) {
                return;
            }

            $allowedSet = array_fill_keys($allowedIds, true);
            $saved = cb_pobocky_load_selected_from_db($idUser);

            $valid = [];
            foreach ($saved as $idPob) {
                if (isset($allowedSet[$idPob])) {
                    $valid[] = (int)$idPob;
                }
            }
            $valid = cb_pobocky_sanitize_ids($valid);

            if ($valid) {
                cb_pobocky_set_selected($valid);
                cb_pobocky_set_mode(count($valid) === 1 ? 'single' : 'custom', null);
                return;
            }

            cb_pobocky_set_selected([(int)$allowedIds[0]]);
            cb_pobocky_set_mode('single', null);
        } catch (Throwable $e) {
            // Tichy fail: hlavicka se vykresli bez predvyberu.
        }
    }
}
