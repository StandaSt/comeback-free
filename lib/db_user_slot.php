<?php
// lib/db_user_slot.php * Verze: V2 * Aktualizace: 12.2.2026 * Počet řádků: 193
declare(strict_types=1);

/*
 * SYNC SLOTY UŽIVATELE (Směny -> Comeback DB)
 *
 * Co to dělá:
 * - vezme sloty přihlášeného uživatele ze session (cb_user_profile['shiftRoleTypeNames'])
 * - převede je na id_slot v tabulce cis_slot (podle názvu slotu)
 * - porovná s aktuálním stavem v user_slot
 * - smaže jen to, co bylo ve Směnách odebráno
 * - přidá jen to, co bylo ve Směnách přidáno
 *
 * Pozn.:
 * - "sloty" jsou ve Směnách názvy (shiftRoleTypeNames), ne ID
 * - nic z API se tady nevolá (bere to jen ze session)
 * - volá se uvnitř transakce z lib/db_user_login.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';

if (!function_exists('db_user_slot_sync')) {

    /**
     * Synchronizuje sloty uživatele podle session.
     *
     * @return array{add:int,del:int,add_names:string[],del_names:string[]}
     */
    function db_user_slot_sync(mysqli $conn, int $idUser, array $profile): array
    {
        $slotyRaw = $profile['shiftRoleTypeNames'] ?? [];
        if (!is_array($slotyRaw)) {
            $slotyRaw = [];
        }

        // 1) desiredNames = unikátní názvy slotů ze Směn
        $desiredNamesMap = [];
        foreach ($slotyRaw as $s) {
            $name = trim((string)$s);
            if ($name === '') {
                continue;
            }
            $desiredNamesMap[$name] = true;
        }
        $desiredNames = array_keys($desiredNamesMap);

        // 2) desiredIds = id_slot z cis_slot (mapujeme přes název)
        $desiredIds = [];
        if (count($desiredNames) > 0) {
            $in = implode(',', array_fill(0, count($desiredNames), '?'));
            $types = str_repeat('s', count($desiredNames));

            $sql = "SELECT id_slot, slot FROM cis_slot WHERE slot IN ($in)";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (cis_slot map).');
            }

            // mysqli::bind_param vyžaduje parametry po referenci → call_user_func_array
            $bind = [];
            $bind[] = &$types;
            foreach ($desiredNames as $i => $v) {
                $desiredNames[$i] = (string)$v;
                $bind[] = &$desiredNames[$i];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
                throw new RuntimeException('DB: bind_param selhal (cis_slot map).');
            }

            $stmt->execute();
            $res = $stmt->get_result();
            if (is_object($res)) {
                while ($row = $res->fetch_assoc()) {
                    $idSlot = (int)$row['id_slot'];
                    $slotName = (string)$row['slot'];
                    $desiredIds[$idSlot] = $slotName;
                }
            }
            $stmt->close();

            // Pokud Směny poslaly sloty, ale my je neumíme namapovat v cis_slot → nic nemažeme, jen zalogujeme.
            if (count($desiredIds) === 0) {
                cb_login_log_line('db_user_slot_map_empty', [
                    'id_user' => (string)$idUser,
                    'sloty' => implode(', ', $desiredNames),
                ]);
                return [
                    'add' => 0,
                    'del' => 0,
                    'add_names' => [],
                    'del_names' => [],
                ];
            }
        }

        // 3) currentIds = id_slot v user_slot
        $currentIds = [];
        $stmt = $conn->prepare('SELECT us.id_slot, cs.slot FROM user_slot us JOIN cis_slot cs ON cs.id_slot = us.id_slot WHERE us.id_user=?');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (user_slot select).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if (is_object($res)) {
            while ($row = $res->fetch_assoc()) {
                $idSlot = (int)$row['id_slot'];
                $slotName = (string)$row['slot'];
                $currentIds[$idSlot] = $slotName;
            }
        }
        $stmt->close();

        // 4) diff
        $toAdd = [];
        foreach ($desiredIds as $idSlot => $name) {
            if (!array_key_exists($idSlot, $currentIds)) {
                $toAdd[$idSlot] = $name;
            }
        }

        $toDel = [];
        foreach ($currentIds as $idSlot => $name) {
            if (!array_key_exists($idSlot, $desiredIds)) {
                $toDel[$idSlot] = $name;
            }
        }

        // 5) delete (odebrané ve Směnách)
        $delCount = 0;
        $delNames = [];
        if (count($toDel) > 0) {
            $stmt = $conn->prepare('DELETE FROM user_slot WHERE id_user=? AND id_slot=?');
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (user_slot delete).');
            }
            foreach ($toDel as $idSlot => $name) {
                $idSlotInt = (int)$idSlot;
                $stmt->bind_param('ii', $idUser, $idSlotInt);
                $stmt->execute();
                $delCount += (int)$stmt->affected_rows;
                $delNames[] = $name;
            }
            $stmt->close();
        }

        // 6) insert (přidané ve Směnách)
        $addCount = 0;
        $addNames = [];
        if (count($toAdd) > 0) {
            $stmt = $conn->prepare('INSERT IGNORE INTO user_slot (id_user, id_slot) VALUES (?, ?)');
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (user_slot insert).');
            }
            foreach ($toAdd as $idSlot => $name) {
                $idSlotInt = (int)$idSlot;
                $stmt->bind_param('ii', $idUser, $idSlotInt);
                $stmt->execute();
                $addCount += (int)$stmt->affected_rows;
                $addNames[] = $name;
            }
            $stmt->close();
        }

        // 7) log: přidáno/odebráno
        if ($addCount > 0) {
            cb_login_log_line('db_user_slot_add', [
                'id_user' => (string)$idUser,
                'count' => (string)$addCount,
                'sloty' => implode(', ', $addNames),
            ]);
        }
        if ($delCount > 0) {
            cb_login_log_line('db_user_slot_del', [
                'id_user' => (string)$idUser,
                'count' => (string)$delCount,
                'sloty' => implode(', ', $delNames),
            ]);
        }

        return [
            'add' => $addCount,
            'del' => $delCount,
            'add_names' => $addNames,
            'del_names' => $delNames,
        ];
    }
}

// lib/db_user_slot.php * Verze: V2 * Aktualizace: 12.2.2026 * Počet řádků: 193
// Konec souboru