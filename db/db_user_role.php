<?php
// db/db_user_role.php * Verze: V4 * Aktualizace: 16.2.2026
declare(strict_types=1);

/*
 * SYNC ROLE UŽIVATELE (Směny -> Comeback DB)
 *
 * Co to dělá:
 * - vezme role přihlášeného uživatele ze session (cb_user_profile['roles'])
 * - převede je na id_role v tabulce cis_role (primárně podle názvu role)
 * - porovná s aktuálním stavem v user_role
 * - smaže jen to, co bylo ve Směnách odebráno
 * - přidá jen to, co bylo ve Směnách přidáno
 *
 * Nově ve V4:
 * - určí „efektivní roli“ pro IS jako nejnižší id_role (MIN)
 * - zapíše ji do comeback.user.id_role (musí existovat sloupec id_role)
 * - uloží ji do session: $_SESSION['cb_role_id'] a $_SESSION['cb_role_name']
 *
 * Pozn.:
 * - tohle není „chyba/nesoulad“, je to běžná synchronizace změn práv
 * - nic z API se tady nevolá (bere to jen ze session)
 * - volá se uvnitř transakce z db/db_user_login.php
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/login_diagnostika.php';

if (!function_exists('db_user_role_sync')) {

    /**
     * Synchronizuje role uživatele podle session.
     *
     * @return array{add:int,del:int,add_names:string[],del_names:string[]}
     */
    function db_user_role_sync(mysqli $conn, int $idUser, array $profile): array
    {
        $rolesRaw = $profile['roles'] ?? [];
        if (!is_array($rolesRaw)) {
            $rolesRaw = [];
        }

        // 1) desiredNames = unikátní názvy rolí ze Směn
        $desiredNamesMap = [];
        foreach ($rolesRaw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = trim((string)($r['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $desiredNamesMap[$name] = true;
        }
        $desiredNames = array_keys($desiredNamesMap);

        // 2) desiredIds = id_role z cis_role (mapujeme přes název, protože id_role ve Směnách nemusí být jisté)
        $desiredIds = [];
        if (count($desiredNames) > 0) {
            $in = implode(',', array_fill(0, count($desiredNames), '?'));
            $types = str_repeat('s', count($desiredNames));

            $sql = "SELECT id_role, role FROM cis_role WHERE role IN ($in) AND aktivni=1";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal (cis_role map).');
            }

            // mysqli::bind_param vyžaduje parametry po referenci → call_user_func_array
            $bind = [];
            $bind[] = &$types;
            foreach ($desiredNames as $i => $v) {
                $desiredNames[$i] = (string)$v;
                $bind[] = &$desiredNames[$i];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
                throw new RuntimeException('DB: bind_param selhal (cis_role map).');
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

            // Pokud Směny poslaly role, ale my je neumíme namapovat v cis_role → nic nemažeme, jen zalogujeme.
            if (count($desiredIds) === 0) {
                cb_login_log_line('db_user_role_map_empty', [
                    'id_user' => (string)$idUser,
                    'roles' => implode(', ', $desiredNames),
                ]);
                return [
                    'add' => 0,
                    'del' => 0,
                    'add_names' => [],
                    'del_names' => [],
                ];
            }
        }

        // 3) currentIds = id_role v user_role
        $currentIds = [];
        $stmt = $conn->prepare('SELECT ur.id_role, cr.role FROM user_role ur JOIN cis_role cr ON cr.id_role = ur.id_role WHERE ur.id_user=?');
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

        // 4) diff
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

        // 5) delete (odebrané ve Směnách)
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

        // 6) insert (přidané ve Směnách)
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

        // 7) efektivní role pro IS = nejnižší id_role
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

            $_SESSION['cb_role_id'] = $idRoleEffective;
            $_SESSION['cb_role_name'] = $roleEffectiveName;

            cb_login_log_line('db_user_role_effective', [
                'id_user' => (string)$idUser,
                'id_role' => (string)$idRoleEffective,
                'role' => $roleEffectiveName,
            ]);
        } else {
            unset($_SESSION['cb_role_id']);
            unset($_SESSION['cb_role_name']);
        }

        // 8) log: přidáno/odebráno
        if ($addCount > 0) {
            cb_login_log_line('db_user_role_add', [
                'id_user' => (string)$idUser,
                'count' => (string)$addCount,
                'roles' => implode(', ', $addNames),
            ]);
        }
        if ($delCount > 0) {
            cb_login_log_line('db_user_role_del', [
                'id_user' => (string)$idUser,
                'count' => (string)$delCount,
                'roles' => implode(', ', $delNames),
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

/* db/db_user_role.php * Verze: V4 * Aktualizace: 16.2.2026 * Počet řádků: 227 */
// Konec souboru