<?php
// db/db_user_slot.php * Verze: V3 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * SYNC SLOTY UĹ˝IVATELE (SmÄ›ny -> Comeback DB)
 *
 * Co to dÄ›lĂˇ:
 * - vezme sloty pĹ™ihlĂˇĹˇenĂ©ho uĹľivatele ze session (cb_user_profile['shiftRoleTypeNames'])
 * - pĹ™evede je na id_slot v tabulce cis_slot (podle nĂˇzvu slotu)
 * - porovnĂˇ s aktuĂˇlnĂ­m stavem v user_slot
 * - smaĹľe jen to, co bylo ve SmÄ›nĂˇch odebrĂˇno
 * - pĹ™idĂˇ jen to, co bylo ve SmÄ›nĂˇch pĹ™idĂˇno
 *
 * Pozn.:
 * - "sloty" jsou ve SmÄ›nĂˇch nĂˇzvy (shiftRoleTypeNames), ne ID
 * - nic z API se tady nevolĂˇ (bere to jen ze session)
 * - volĂˇ se uvnitĹ™ transakce z db/db_user_login.php
 */

if (!function_exists('db_user_slot_sync')) {

    /**
     * Synchronizuje sloty uĹľivatele podle session.
     *
     * @return array{add:int,del:int,add_names:string[],del_names:string[]}
     */
    function db_user_slot_sync(mysqli $conn, int $idUser, array $profile): array
    {
        $slotyRaw = $profile['shiftRoleTypeNames'] ?? [];
        if (!is_array($slotyRaw)) {
            $slotyRaw = [];
        }

        // 1) desiredNames = unikĂˇtnĂ­ nĂˇzvy slotĹŻ ze SmÄ›n
        $desiredNamesMap = [];
        foreach ($slotyRaw as $s) {
            $name = trim((string)$s);
            if ($name === '') {
                continue;
            }
            $desiredNamesMap[$name] = true;
        }
        $desiredNames = array_keys($desiredNamesMap);

        // 2) desiredIds = id_slot z cis_slot (mapujeme pĹ™es nĂˇzev)
        $desiredIds = [];
        if (count($desiredNames) > 0) {
            $in = implode(',', array_fill(0, count($desiredNames), '?'));
            $types = str_repeat('s', count($desiredNames));

            $sql = "SELECT id_slot, slot FROM cis_slot WHERE slot IN ($in)";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (cis_slot map).');
            }

            // mysqli::bind_param vyĹľaduje parametry po referenci â†’ call_user_func_array
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

            // Pokud SmÄ›ny poslaly sloty, ale my je neumĂ­me namapovat v cis_slot â†’ nic nemaĹľeme, jen zalogujeme.
            if (count($desiredIds) === 0) {
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

        // 5) delete (odebranĂ© ve SmÄ›nĂˇch)
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

        // 6) insert (pĹ™idanĂ© ve SmÄ›nĂˇch)
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

        // 7) log: pĹ™idĂˇno/odebrĂˇno
        if ($addCount > 0) {
        }
        if ($delCount > 0) {
        }

        return [
            'add' => $addCount,
            'del' => $delCount,
            'add_names' => $addNames,
            'del_names' => $delNames,
        ];
    }
}

/* db/db_user_slot.php * Verze: V3 * Aktualizace: 12.2.2026 * PoÄŤet Ĺ™ĂˇdkĹŻ: 193 */
// Konec souboru
