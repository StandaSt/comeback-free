<?php
// db/db_user_login.php * Verze: V7 * Aktualizace: 16.2.2026
declare(strict_types=1);

/*
 * DB SYNC PO PŘIHLÁŠENÍ (Směny -> Comeback DB)
 *
 * Tenhle soubor je „orchestr“:
 * - bere data ze session (už je naplnil login_smeny.php)
 * - volá malé funkce pro DB (user, pobocka, povoleni, role, slot)
 * - vše v transakci
 *
 * Důležité:
 * - NESMÍ volat Směny (API)
 * - při chybě vyhodí výjimku (login_smeny.php to chytí a přihlášení zruší)
 *
 * Pozn. (login log):
 * - ZÁPIS do user_login + user_spy probíhá tady (v DB vrstvě), ne v lib/
 * - 1 úspěšný login = přesně 1 řádek v user_login (akce=1) + 1 řádek v user_spy
 *
 * V7:
 * - po synchronizaci rolí určí „efektivní roli“ jako MIN(id_role) z user_role
 *   a uloží ji do:
 *   - comeback.user.id_role
 *   - $_SESSION['cb_user']['id_role'] + $_SESSION['cb_user']['role'] (název z cis_role)
 * - po vložení aktuálního loginu (user_login) načte z DB „login info“ do session,
 *   aby hlavička uměla zobrazit diagnostické údaje bez dalších DB dotazů:
 *   - poslední přístup (předchozí úspěšný login) – čas + IP
 *   - statistika přihlášení – celkem / dnes (jen akce=1)
 *   - aktuální login – čas + IP (podle id_login)
 * - založí session údaje pro odpočítávání neaktivity:
 *   - cb_timeout_min (zatím 20)
 *   - cb_last_activity_ts (time())
 *   - cb_session_start_ts (time())
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/login_diagnostika.php';
require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/db_pobocka.php';
require_once __DIR__ . '/db_povoleni.php';
require_once __DIR__ . '/db_user_role.php';
require_once __DIR__ . '/db_user_slot.php';

if (!function_exists('cb_db_user_login')) {

    /**
     * Vloží záznam o přihlášení do user_login a navazující user_spy.
     *
     * Co ukládáme:
     * - user_login: id_user, session_id, kdy (auto), akce=1, duvod=0, ip
     * - user_spy:  id_login (FK), id_user, user_agent (+ do budoucna screen/touch)
     *
     * Vrací:
     * - id_login z tabulky user_login (AUTO_INCREMENT)
     *
     * Vedlejší efekt:
     * - uloží $_SESSION['cb_id_login'] = id_login (hodí se pro další části IS)
     */
    function cb_db_insert_login_and_spy(mysqli $conn, int $idUser): int
    {
        $sessionId = (string)session_id();

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip)) {
            $ip = trim($ip);
            if ($ip === '') {
                $ip = null;
            }
        } else {
            $ip = null;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (is_string($ua)) {
            $ua = trim($ua);
            if ($ua === '') {
                $ua = null;
            }
        } else {
            $ua = null;
        }

        // 1) user_login (akce=1, duvod=0)
        $stmt = $conn->prepare(
            'INSERT INTO user_login (id_user, session_id, akce, duvod, ip) VALUES (?,?,1,0,?)'
        );
        $stmt->bind_param('iss', $idUser, $sessionId, $ip);
        $stmt->execute();
        $stmt->close();

        $idLogin = (int)$conn->insert_id;

        // 2) user_spy (zatím jen UA; screen_w/screen_h/is_touch zůstanou NULL)
        $stmt = $conn->prepare(
            'INSERT INTO user_spy (id_login, id_user, user_agent, screen_w, screen_h, is_touch)
             VALUES (?,?,?,NULL,NULL,NULL)'
        );
        $stmt->bind_param('iis', $idLogin, $idUser, $ua);
        $stmt->execute();
        $stmt->close();

        // pro další vrstvy (např. logout, info bloky) si můžeme držet id_login v session
        $_SESSION['cb_id_login'] = $idLogin;

        return $idLogin;
    }

    /**
     * Efektivní role pro IS:
     *
     * Směny mohou vrátit více rolí (např. zaměstnanec + manager).
     * Pro řízení práv v IS používáme jedno číslo:
     * - MIN(id_role) z tabulky user_role (nejnižší ID = nejsilnější role)
     *
     * Co to udělá:
     * - z DB zjistí MIN(id_role) pro id_user
     * - zapíše do comeback.user.id_role
     * - načte název role z cis_role
     * - uloží do session:
     *   $_SESSION['cb_user']['id_role'] = <int>
     *   $_SESSION['cb_user']['role']    = <string>  (pro UI)
     */
    function cb_db_set_effective_role(mysqli $conn, int $idUser): void
    {
        $stmt = $conn->prepare('SELECT MIN(id_role) AS min_role FROM user_role WHERE id_user=?');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (min role).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($minRole);
        $stmt->fetch();
        $stmt->close();

        $idRole = (int)$minRole;
        if ($idRole <= 0) {
            // neočekávaný stav: uživatel nemá žádnou roli
            if (isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user'])) {
                unset($_SESSION['cb_user']['id_role'], $_SESSION['cb_user']['role']);
            }
            return;
        }

        // zapiš do user.id_role (aby bylo vidět v DB bez session)
        $stmt = $conn->prepare('UPDATE user SET id_role=? WHERE id_user=?');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (user id_role update).');
        }
        $stmt->bind_param('ii', $idRole, $idUser);
        $stmt->execute();
        $stmt->close();

        // název role pro UI (cis_role)
        $roleName = '';
        $stmt = $conn->prepare('SELECT role FROM cis_role WHERE id_role=? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (cis_role name).');
        }
        $stmt->bind_param('i', $idRole);
        $stmt->execute();
        $stmt->bind_result($roleNameDb);
        if ($stmt->fetch()) {
            $roleName = (string)$roleNameDb;
        }
        $stmt->close();

        if (!isset($_SESSION['cb_user']) || !is_array($_SESSION['cb_user'])) {
            $_SESSION['cb_user'] = [];
        }
        $_SESSION['cb_user']['id_role'] = $idRole;
        $_SESSION['cb_user']['role'] = $roleName;

        cb_login_log_line('db_user_role_effective', [
            'id_user' => (string)$idUser,
            'id_role' => (string)$idRole,
            'role' => $roleName,
        ]);
    }

    /**
     * Načte login informace pro hlavičku a uloží je do session.
     *
     * Proč:
     * - hlavičku chceme renderovat bez DB dotazů
     * - tyhle hodnoty se mají měnit až při dalším přihlášení
     *
     * Co uloží do session:
     * - $_SESSION['cb_login_info'] = [
     *     'current' => ['kdy' => 'YYYY-mm-dd HH:ii:ss', 'ip' => 'x.x.x.x'],
     *     'prev'    => ['kdy' => '...', 'ip' => '...'] | null,
     *     'stats'   => ['total' => int, 'today' => int],
     *   ]
     *
     * Pozn.:
     * - „prev“ = předchozí úspěšný login (akce=1) pro stejného uživatele
     * - „today“ = počet úspěšných loginů dnes (podle serverového CURDATE())
     */
    function cb_db_fill_login_info_session(mysqli $conn, int $idUser, int $idLogin): void
    {
        $currentKdy = null;
        $currentIp = null;

        // A) aktuální login (podle id_login)
        $stmt = $conn->prepare('SELECT kdy, ip FROM user_login WHERE id_login=? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (current login).');
        }
        $stmt->bind_param('i', $idLogin);
        $stmt->execute();
        $stmt->bind_result($kdyDb, $ipDb);
        if ($stmt->fetch()) {
            $currentKdy = is_string($kdyDb) ? $kdyDb : null;
            $currentIp = is_string($ipDb) ? $ipDb : null;
        }
        $stmt->close();

        // B) předchozí úspěšný login (akce=1), mimo právě vložený řádek
        $prevKdy = null;
        $prevIp = null;

        $stmt = $conn->prepare(
            'SELECT kdy, ip
             FROM user_login
             WHERE id_user=? AND akce=1 AND id_login<>?
             ORDER BY kdy DESC, id_login DESC
             LIMIT 1'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (prev login).');
        }
        $stmt->bind_param('ii', $idUser, $idLogin);
        $stmt->execute();
        $stmt->bind_result($pkdyDb, $pipDb);
        if ($stmt->fetch()) {
            $prevKdy = is_string($pkdyDb) ? $pkdyDb : null;
            $prevIp = is_string($pipDb) ? $pipDb : null;
        }
        $stmt->close();

        // C) statistika loginů (jen úspěšné: akce=1)
        $total = 0;
        $today = 0;

        $stmt = $conn->prepare('SELECT COUNT(*) FROM user_login WHERE id_user=? AND akce=1');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (login total).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($cnt);
        if ($stmt->fetch()) {
            $total = (int)$cnt;
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT COUNT(*) FROM user_login WHERE id_user=? AND akce=1 AND DATE(kdy)=CURDATE()');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (login today).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($cnt2);
        if ($stmt->fetch()) {
            $today = (int)$cnt2;
        }
        $stmt->close();

        $_SESSION['cb_login_info'] = [
            'current' => [
                'kdy' => $currentKdy,
                'ip' => $currentIp,
                'id_login' => $idLogin,
            ],
            'prev' => ($prevKdy !== null || $prevIp !== null) ? [
                'kdy' => $prevKdy,
                'ip' => $prevIp,
            ] : null,
            'stats' => [
                'total' => $total,
                'today' => $today,
            ],
        ];
    }

    /**
     * Inicializuje session údaje pro timeout neaktivity.
     *
     * Jak to chceme používat:
     * - v prohlížeči poběží minutový timer, který:
     *   - z last_activity_ts + timeout_min vypočítá „zbývá“
     *   - při vypršení zavolá logout
     *
     * Serverová část (tady) udělá jen „start hodnoty“ po loginu.
     */
    function cb_db_init_timeout_session(): void
    {
        // zatím pevně, později půjde z administrace (DB)
        $_SESSION['cb_timeout_min'] = 20;

        // kdy naposledy proběhla uživatelská akce (na startu = teď)
        $_SESSION['cb_last_activity_ts'] = time();

        // kdy začala tahle přihlášená session (na startu = teď)
        $_SESSION['cb_session_start_ts'] = time();
    }

    /**
     * Hlavní funkce – volá se z login_smeny.php po úspěšném loginu do Směn.
     */
    function cb_db_user_login(): void
    {
        $profile = $_SESSION['cb_user_profile'] ?? null;
        $branches = $_SESSION['cb_user_branches'] ?? null;

        if (!is_array($profile) || empty($profile['id'])) {
            throw new RuntimeException('Chybí profil uživatele v session (cb_user_profile).');
        }
        if (!is_array($branches)) {
            throw new RuntimeException('Chybí pobočky uživatele v session (cb_user_branches).');
        }

        $idUser = (int)$profile['id'];

        // workingBranchNames = seznam kódů poboček (stringy)
        $working = $branches['workingBranchNames'] ?? [];
        if (!is_array($working)) {
            $working = [];
        }

        // normalizace: string + bez prázdných položek
        $workingCodes = [];
        foreach ($working as $code) {
            $code = trim((string)$code);
            if ($code !== '') {
                $workingCodes[] = $code;
            }
        }

        $conn = db();
        $conn->begin_transaction();

        try {
            cb_login_log_line('db_sync_start', [
                'id_user' => (string)$idUser,
                'branches' => (string)count($workingCodes),
            ]);

            // A) user (profil)
            cb_db_upsert_user($conn, $profile);

            // B) pobocka (zajistit kódy)
            $desiredPobIds = cb_db_ensure_branches_get_ids($conn, $workingCodes);

            // C) povolení (aktuální + historie)
            $permChanges = cb_db_sync_permissions($conn, $idUser, $desiredPobIds);

            // D) role (aktuální) – podle Směn (data už jsou v $profile ze session)
            $roleChanges = db_user_role_sync($conn, $idUser, $profile);

            // D2) efektivní role (MIN id_role) -> user.id_role + session
            cb_db_set_effective_role($conn, $idUser);

            // E) sloty (aktuální) – podle Směn (data už jsou v $profile ze session)
            $slotChanges = db_user_slot_sync($conn, $idUser, $profile);

            // F) login event (akce=1) + user_spy
            $idLogin = cb_db_insert_login_and_spy($conn, $idUser);

            // G) login info do session (pro hlavičku)
            cb_db_fill_login_info_session($conn, $idUser, $idLogin);

            $conn->commit();

            // H) timeout session (nemá DB, jen session)
            cb_db_init_timeout_session();

            cb_login_log_line('db_sync_ok', [
                'id_user' => (string)$idUser,
                'id_login' => (string)$idLogin,

                // povolení poboček
                'pob_add' => (string)($permChanges['add'] ?? 0),
                'pob_del' => (string)($permChanges['del'] ?? 0),

                // role
                'role_add' => (string)($roleChanges['add'] ?? 0),
                'role_del' => (string)($roleChanges['del'] ?? 0),

                // sloty
                'slot_add' => (string)($slotChanges['add'] ?? 0),
                'slot_del' => (string)($slotChanges['del'] ?? 0),
            ]);

        } catch (Throwable $e) {
            $conn->rollback();
            cb_login_log_line('db_sync_fail', ['id_user' => (string)$idUser], $e);
            throw $e;
        }
    }
}

// db/db_user_login.php * Verze: V7 * Aktualizace: 16.2.2026 * Počet řádků: 404
// Konec souboru