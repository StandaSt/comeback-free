<?php
// db/db_user_role.php * Verze: V5 * Aktualizace: 21.2.2026
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
 * Efektivní role pro IS:
 * - Směny mohou vrátit více rolí (např. zaměstnanec + manager).
 * - Pro řízení práv v IS používáme jedno číslo:
 *   nejnižší id_role (MIN) z rolí namapovaných ze Směn.
 *
 * Co ukládáme:
 * - DB:
 *   - tabulka user_role = všechny role uživatele (aktuální stav)
 *   - tabulka user.id_role = efektivní role (MIN)
 * - SESSION:
 *   - pouze do $_SESSION['cb_user']:
 *     - $_SESSION['cb_user']['id_role'] = <int>
 *     - $_SESSION['cb_user']['role']    = <string>
 *
 * Pozn.:
 * - nic z API se tady nevolá (bere to jen ze session)
 * - volá se uvnitř transakce z db/db_user_login.php
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/login_diagnostika.php';

if (!function_exists('db_user_role_sync')) {

    /**
     * Synchronizuje role uživatele podle session.
     *
     * Vstup:
     * - $profile['roles'] ... role ze Směn (pole objektů, typicky s klíčem 'name')
     *
     * Výstup:
     * - add/del počty + seznamy názvů rolí, které se přidaly/odebraly.
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

        // 2) desiredIds = id_role z cis_role (mapujeme přes název role)
        //    Struktura:
        //    - $desiredIds[<id_role>] = <název role>
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

            // Pokud Směny poslaly role, ale my je neumíme namapovat v cis_role:
            // - nic nemažeme (abychom neodstřelili role omylem)
            // - jen zalogujeme a skončíme bez změn
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

        // 3) currentIds = aktuální role uživatele v DB (user_role)
        //    Struktura:
        //    - $currentIds[<id_role>] = <název role>
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

        // 4) diff (co přidat / co smazat)
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

        // 7) efektivní role pro IS = nejnižší id_role (MIN) z rolí ze Směn
        //    - zapíšeme do user.id_role
        //    - uložíme do session (jen do cb_user)
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

            if (!isset($_SESSION['cb_user']) || !is_array($_SESSION['cb_user'])) {
                $_SESSION['cb_user'] = [];
            }
            $_SESSION['cb_user']['id_role'] = $idRoleEffective;
            $_SESSION['cb_user']['role'] = $roleEffectiveName;

            cb_login_log_line('db_user_role_effective', [
                'id_user' => (string)$idUser,
                'id_role' => (string)$idRoleEffective,
                'role' => $roleEffectiveName,
            ]);
        } else {
            // Směny nevrátily žádnou roli:
            // - v session odstraníme jen údaje o efektivní roli
            // - do DB user.id_role už teď nesahejme (necháváme poslední známý stav)
            if (isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user'])) {
                unset($_SESSION['cb_user']['id_role'], $_SESSION['cb_user']['role']);
            }
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

// db/db_user_role.php * Verze: V5 * Aktualizace: 21.2.2026
// Počet řádků: 262
// Konec souboru