<?php
// db/db_user_role.php * Verze: V6 * Aktualizace: 02.04.2026
declare(strict_types=1);

/*
 * SYNC ROLE UĹ˝IVATELE (SmÄ›ny -> Comeback DB)
 *
 * Co to dÄ›lĂˇ:
 * - vezme role pĹ™ihlĂˇĹˇenĂ©ho uĹľivatele ze session (cb_user_profile['roles'])
 * - pĹ™evede je pĹ™es cis_role.id_role_smeny na naĹˇe id_role
 * - porovnĂˇ s aktuĂˇlnĂ­m stavem v user_role
 * - smaĹľe jen to, co bylo ve SmÄ›nĂˇch odebrĂˇno
 * - pĹ™idĂˇ jen to, co bylo ve SmÄ›nĂˇch pĹ™idĂˇno
 *
 * EfektivnĂ­ role pro IS:
 * - SmÄ›ny mohou vrĂˇtit vĂ­ce rolĂ­ (napĹ™. zamÄ›stnanec + manager).
 * - Pro Ĺ™Ă­zenĂ­ prĂˇv v IS pouĹľĂ­vĂˇme jedno ÄŤĂ­slo:
 *   nejniĹľĹˇĂ­ id_role (MIN) z rolĂ­ namapovanĂ˝ch ze SmÄ›n.
 *
 * Co uklĂˇdĂˇme:
 * - DB:
 *   - tabulka user_role = vĹˇechny role uĹľivatele (aktuĂˇlnĂ­ stav)
 *   - tabulka user.id_role = efektivnĂ­ role (MIN)
 * - SESSION:
 *   - pouze do $_SESSION['cb_user']:
 *     - $_SESSION['cb_user']['id_role'] = <int>
 *     - $_SESSION['cb_user']['role']    = <string>
 *     - $_SESSION['cb_user']['sub_role'] = 4|5 (jen kdyz existuje)
 *
 * Pozn.:
 * - nic z API se tady nevolĂˇ (bere to jen ze session)
 * - volĂˇ se uvnitĹ™ transakce z db/db_user_login.php
 */

if (!function_exists('db_user_role_sync')) {

    /**
     * Synchronizuje role uĹľivatele podle session.
     *
     * Vstup:
     * - $profile['roles'] ... role ze SmÄ›n (pole objektĹŻ, typicky s klĂ­ÄŤem 'name')
     *
     * VĂ˝stup:
     * - add/del poÄŤty + seznamy nĂˇzvĹŻ rolĂ­, kterĂ© se pĹ™idaly/odebraly
     * - sub_role = 4 nebo 5 podle rolĂ­ ze SmÄ›n (kdyĹľ mĂˇ obÄ›, bere se 4)
     *
     * @return array{add:int,del:int,add_names:string[],del_names:string[],sub_role:?int}
     */
    function db_user_role_sync(mysqli $conn, int $idUser, array $profile, bool $updateSession = true): array
    {
        $rolesRaw = $profile['roles'] ?? [];
        if (!is_array($rolesRaw)) {
            $rolesRaw = [];
        }

        // 1) desiredRawRoles = unikĂˇtnĂ­ role ze SmÄ›n podle jejich id
        $desiredRawRoles = [];
        foreach ($rolesRaw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $rawRoleId = (int)($r['id'] ?? 0);
            if ($rawRoleId <= 0) {
                continue;
            }
            $name = trim((string)($r['name'] ?? ''));
            $desiredRawRoles[$rawRoleId] = $name;
        }
        $desiredRawIds = array_keys($desiredRawRoles);

        // 1b) sub_role = jen vedouci pobocky (4) / vedouci smeny (5)
        //     - bere se primo z id role vraceneho ze Smen
        //     - kdyz ma obe, ulozi se mensi = 4
        $subRole = null;
        foreach ($rolesRaw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $rawRoleId = (int)($r['id'] ?? 0);
            if ($rawRoleId !== 4 && $rawRoleId !== 5) {
                continue;
            }
            if ($subRole === null || $rawRoleId < $subRole) {
                $subRole = $rawRoleId;
            }
        }

        // 2) desiredIds = naĹˇe id_role pĹ™es cis_role.id_role_smeny
        //    Struktura:
        //    - $desiredIds[<id_role>] = <nĂˇzev role>
        $desiredIds = [];
        if (count($desiredRawIds) > 0) {
            $in = implode(',', array_fill(0, count($desiredRawIds), '?'));
            $types = str_repeat('i', count($desiredRawIds));

            $sql = "
                SELECT id_role, role
                FROM cis_role
                WHERE id_role_smeny IN ($in)
                  AND aktivni = 1
            ";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (cis_role id_role_smeny).');
            }

            // mysqli::bind_param vyĹľaduje parametry po referenci â†’ call_user_func_array
            $bind = [];
            $bind[] = &$types;
            foreach ($desiredRawIds as $i => $v) {
                $desiredRawIds[$i] = (int)$v;
                $bind[] = &$desiredRawIds[$i];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
                throw new RuntimeException('DB: bind_param selhal (cis_role id_role_smeny).');
            }

            $stmt->execute();
            $res = $stmt->get_result();
            if (is_object($res)) {
                while ($row = $res->fetch_assoc()) {
                    $idRole = (int)$row['id_role'];
                    $roleName = (string)$row['role'];
                    $desiredIds[$idRole] = $roleName;
                }
            }
            $stmt->close();

            // Pokud SmÄ›ny poslaly role, ale my je neumĂ­me namapovat:
            // - nic nemaĹľeme (abychom neodstĹ™elili role omylem)
            // - jen zalogujeme a skonÄŤĂ­me bez zmÄ›n
            if (count($desiredIds) === 0) {
                return [
                    'add' => 0,
                    'del' => 0,
                    'add_names' => [],
                    'del_names' => [],
                    'sub_role' => null,
                ];
            }
        }

        // 3) currentIds = aktuĂˇlnĂ­ role uĹľivatele v DB (user_role)
        //    Struktura:
        //    - $currentIds[<id_role>] = <nĂˇzev role>
        $currentIds = [];
        $stmt = $conn->prepare(
            'SELECT ur.id_role, cr.role
             FROM user_role ur
             JOIN cis_role cr ON cr.id_role = ur.id_role
             WHERE ur.id_user=?'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (user_role select).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if (is_object($res)) {
            while ($row = $res->fetch_assoc()) {
                $idRole = (int)$row['id_role'];
                $roleName = (string)$row['role'];
                $currentIds[$idRole] = $roleName;
            }
        }
        $stmt->close();

        // 4) diff (co pĹ™idat / co smazat)
        $toAdd = [];
        foreach ($desiredIds as $idRole => $name) {
            if (!array_key_exists($idRole, $currentIds)) {
                $toAdd[$idRole] = $name;
            }
        }

        $toDel = [];
        foreach ($currentIds as $idRole => $name) {
            if (!array_key_exists($idRole, $desiredIds)) {
                $toDel[$idRole] = $name;
            }
        }

        // 5) delete (odebranĂ© ve SmÄ›nĂˇch)
        $delCount = 0;
        $delNames = [];
        if (count($toDel) > 0) {
            $stmt = $conn->prepare('DELETE FROM user_role WHERE id_user=? AND id_role=?');
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (user_role delete).');
            }
            foreach ($toDel as $idRole => $name) {
                $idRoleInt = (int)$idRole;
                $stmt->bind_param('ii', $idUser, $idRoleInt);
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
            $stmt = $conn->prepare('INSERT IGNORE INTO user_role (id_user, id_role) VALUES (?, ?)');
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (user_role insert).');
            }
            foreach ($toAdd as $idRole => $name) {
                $idRoleInt = (int)$idRole;
                $stmt->bind_param('ii', $idUser, $idRoleInt);
                $stmt->execute();
                $addCount += (int)$stmt->affected_rows;
                $addNames[] = $name;
            }
            $stmt->close();
        }

        // 7) efektivnĂ­ role pro IS = nejniĹľĹˇĂ­ id_role (MIN) z rolĂ­ ze SmÄ›n
        //    - zapĂ­Ĺˇeme do user.id_role
        //    - uloĹľĂ­me do session (jen do cb_user)
        if (count($desiredIds) > 0) {
            $idRoleEffective = min(array_keys($desiredIds));
            $roleEffectiveName = (string)($desiredIds[$idRoleEffective] ?? '');

            $stmt = $conn->prepare('UPDATE user SET id_role=? WHERE id_user=?');
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (user id_role update).');
            }
            $stmt->bind_param('ii', $idRoleEffective, $idUser);
            $stmt->execute();
            $stmt->close();

            if ($updateSession) {
                if (!isset($_SESSION['cb_user']) || !is_array($_SESSION['cb_user'])) {
                    $_SESSION['cb_user'] = [];
                }
                $_SESSION['cb_user']['id_role'] = $idRoleEffective;
                $_SESSION['cb_user']['role'] = $roleEffectiveName;
            }

        } else {
            // SmÄ›ny nevrĂˇtily ĹľĂˇdnou roli:
            // - v session odstranĂ­me jen Ăşdaje o efektivnĂ­ roli
            // - do DB user.id_role uĹľ teÄŹ nesahejme (nechĂˇvĂˇme poslednĂ­ znĂˇmĂ˝ stav)
            if ($updateSession && isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user'])) {
                unset($_SESSION['cb_user']['id_role'], $_SESSION['cb_user']['role']);
            }
        }

        // 8) sub_role = 4 nebo 5 (vedouci pobocky / vedouci smeny)
        //    - zapisujeme jen kdyz existuje
        //    - kdyz neexistuje, vycistime stare hodnoty v DB i session
        if ($subRole !== null) {
            $stmtSubRole = $conn->prepare('UPDATE user_role SET sub_role=? WHERE id_user=?');
            if ($stmtSubRole === false) {
                throw new RuntimeException('DB: prepare selhal (user_role sub_role update).');
            }
            $stmtSubRole->bind_param('ii', $subRole, $idUser);
            $stmtSubRole->execute();
            $stmtSubRole->close();

            if ($updateSession) {
                if (!isset($_SESSION['cb_user']) || !is_array($_SESSION['cb_user'])) {
                    $_SESSION['cb_user'] = [];
                }
                $_SESSION['cb_user']['sub_role'] = $subRole;
            }

        } else {
            $stmtSubRoleNull = $conn->prepare('UPDATE user_role SET sub_role=NULL WHERE id_user=?');
            if ($stmtSubRoleNull === false) {
                throw new RuntimeException('DB: prepare selhal (user_role sub_role null update).');
            }
            $stmtSubRoleNull->bind_param('i', $idUser);
            $stmtSubRoleNull->execute();
            $stmtSubRoleNull->close();

            if ($updateSession && isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user'])) {
                unset($_SESSION['cb_user']['sub_role']);
            }
        }

        // 9) log: pridano/odebrano
        if ($addCount > 0) {
        }
        if ($delCount > 0) {
        }

        return [
            'add' => $addCount,
            'del' => $delCount,
            'add_names' => $addNames,
            'del_names' => $delNames,
            'sub_role' => $subRole,
        ];
    }
}

if (!function_exists('db_user_role_effective_id')) {
    /**
     * Vrati efektivni roli uzivatele jako nejmensi id_role z tabulky user_role.
     * Kdyz uzivatel nema zadnou roli, vraci null.
     */
    function db_user_role_effective_id(mysqli $conn, int $idUser): ?int
    {
        if ($idUser <= 0) {
            return null;
        }

        $stmt = $conn->prepare('
            SELECT MIN(id_role) AS min_role
            FROM user_role
            WHERE id_user=?
        ');
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($minRole);

        $out = null;
        if ($stmt->fetch()) {
            $v = (int)$minRole;
            if ($v > 0) {
                $out = $v;
            }
        }
        $stmt->close();
        return $out;
    }
}

// db/db_user_role.php * Verze: V6 * Aktualizace: 02.04.2026
// PoÄŤet Ĺ™ĂˇdkĹŻ: 353
// Konec souboru
