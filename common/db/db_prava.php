<?php
declare(strict_types=1);

/*
 * Jednotne cte prava a jejich zdroje; prihlaseni je uklada do session a spravni prehledy pouze ctou.
 * Globalni prava jsou sjednocenim pristupovych profilu osoby v HR.
 * Prebrana prava USER 1 zachovava jako snimek platny do odhlaseni.
 */
require_once __DIR__ . '/../lib/prebrani_prav.php';

if (!function_exists('cb_db_user_role_data')) {
    /** @return array<int,string> Role uživatele indexované podle id_role. */
    function cb_db_user_role_data(mysqli $conn, int $idUser, bool $skutecne=false): array
    {
        if (!$skutecne && ($profile = cb_prebrani_prav_profil($idUser)) !== null) {
            return array_column($profile['roles'], 'name', 'id_role');
        }
        if ($idUser <= 0) {
            return [];
        }

        $stmt = $conn->prepare('
            SELECT ur.id_role, cr.role
            FROM hr_pristupovy_profil AS ur
            INNER JOIN cis_role AS cr ON cr.id_role = ur.id_role
            WHERE ur.id_person = ?
              AND cr.aktivni = 1
            ORDER BY ur.id_role ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB: načtení rolí uživatele selhalo.');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[(int)$row['id_role']] = (string)$row['role'];
        }
        $stmt->close();
        return $roles;
    }
}

if (!function_exists('cb_user_ma_roli')) {
    function cb_user_ma_roli(int $idRole): bool
    {
        if ($idRole <= 0) {
            return false;
        }
        $roles = $_SESSION['cb_user']['roles'] ?? [];
        if (!is_array($roles)) {
            return false;
        }
        foreach ($roles as $role) {
            if (is_array($role) && (int)($role['id_role'] ?? 0) === $idRole) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('cb_db_prava_cti')) {
    /** Ciste cteni skutecnych prav vcetne zdroju; vyjimka prebiji role, zadna session se pri cteni nemeni. */
    function cb_db_prava_cti(mysqli $conn, int $idUser): array
    {
        $out=['prava'=>[],'prava_stav'=>[],'roles'=>cb_db_user_role_data($conn,$idUser,true),'duvody'=>[]];

        $resRights = $conn->query('SELECT id_pravo, aktivni FROM cis_prava ORDER BY id_pravo');
        if (!($resRights instanceof mysqli_result)) {
            throw new RuntimeException('DB: načtení číselníku práv selhalo.');
        }
        while ($row = $resRights->fetch_assoc()) {
            $idPravo = (int)$row['id_pravo'];
            if ($idPravo > 0) {
                $out['prava_stav'][$idPravo] = (int)$row['aktivni'] === 1 ? 1 : 0;
            }
        }
        $resRights->free();

        if ($idUser <= 0 || $out['roles'] === []) {
            return $out;
        }

        $stmtGlobal = $conn->prepare('
            SELECT DISTINCT globalni.id_pravo, globalni.id_role, zdroj_role.role
            FROM hr_pristupovy_profil AS uzivatelska_role
            INNER JOIN prava_global AS globalni
                ON globalni.id_role = uzivatelska_role.id_role
            INNER JOIN cis_prava AS pravo
                ON pravo.id_pravo = globalni.id_pravo
               AND pravo.aktivni = 1
            LEFT JOIN cis_role AS zdroj_role ON zdroj_role.id_role=globalni.id_role
            WHERE uzivatelska_role.id_person = ?
        ');
        if ($stmtGlobal === false) {
            throw new RuntimeException('DB: prepare selhal (prava_global select).');
        }

        $stmtGlobal->bind_param('i', $idUser);
        $stmtGlobal->execute();
        $resGlobal = $stmtGlobal->get_result();
        while ($row = $resGlobal->fetch_assoc()) {
            $idPravo = (int)$row['id_pravo'];
            if ($idPravo > 0) {
                $out['prava'][$idPravo] = 1;
                $out['duvody'][$idPravo]['roles'][(int)$row['id_role']]=(string)($row['role']??('Role '.$row['id_role']));
            }
        }
        $stmtGlobal->close();

        $stmtVyjimky = $conn->prepare('
            SELECT vyjimka.id_pravo, vyjimka.povoleno
            FROM prava_vyjimky AS vyjimka
            INNER JOIN cis_prava AS pravo
                ON pravo.id_pravo = vyjimka.id_pravo
               AND pravo.aktivni = 1
            WHERE vyjimka.id_user = ?
        ');
        if ($stmtVyjimky === false) {
            throw new RuntimeException('DB: prepare selhal (prava_vyjimky select).');
        }

        $stmtVyjimky->bind_param('i', $idUser);
        $stmtVyjimky->execute();
        $resVyjimky = $stmtVyjimky->get_result();
        while ($row = $resVyjimky->fetch_assoc()) {
            $idPravo = (int)$row['id_pravo'];
            if ($idPravo <= 0) {
                continue;
            }

            $povoleno = $row['povoleno'];
            if ($povoleno !== null && (int)$povoleno === 1) {
                $out['prava'][$idPravo] = 1;
                $out['duvody'][$idPravo]['individual']=true;
            } elseif ($povoleno !== null && (int)$povoleno === 0) {
                unset($out['prava'][$idPravo]);
                $out['duvody'][$idPravo]['individual']=false;
            }
        }
        $stmtVyjimky->close();
        return $out;
    }
}

if (!function_exists('cb_db_prava_nacti_do_session')) {
    /** Prihlaseni pouziva stejne cteni jako prehled; prevzata prava zustavaji oddelenym nemennym snimkem. */
    function cb_db_prava_nacti_do_session(mysqli $conn, int $idUser): void
    {
        if (($profile = cb_prebrani_prav_profil($idUser)) !== null) {
            $_SESSION['prava']=$profile['prava'];$_SESSION['prava_stav']=$profile['prava_stav'];
            $_SESSION['cb_user']['roles']=$profile['roles'];$_SESSION['cb_user']['role']=$profile['role'];return;
        }
        // Chyba DB nesmi ponechat stara prava z predchoziho obnoveni session.
        $_SESSION['prava']=[];$_SESSION['prava_stav']=[];
        $data=cb_db_prava_cti($conn,$idUser);
        $_SESSION['prava']=$data['prava'];$_SESSION['prava_stav']=$data['prava_stav'];
        if(!isset($_SESSION['cb_user']) || !is_array($_SESSION['cb_user']))$_SESSION['cb_user']=[];
        $_SESSION['cb_user']['roles']=[];
        foreach($data['roles'] as $idRole=>$roleName)$_SESSION['cb_user']['roles'][]=['id_role'=>$idRole,'name'=>$roleName];
        $_SESSION['cb_user']['role']=implode(', ',array_values($data['roles']));unset($_SESSION['cb_user']['id_role']);
    }
}
