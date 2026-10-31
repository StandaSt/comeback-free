<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

/*
 * Ucel souboru: Zobrazit uzivatele po profilech zarizeni za provozni den od 06:00,
 * jejich prvni prihlaseni, posledni skutecnou akci a stav online/odhlasen.
 * Technicky heartbeat zarizeni neni uzivatelska aktivita.
 * Jen v tomto bloku barvi jmena podle skutecne HR role zobrazovane osoby.
 * Opakovana prihlaseni ze stejneho profilu sdileji radek i po odhlaseni.
 * Android a iPhone oznacuje ikonou mobilu; vice PC profilu rozlisuje PC1, PC2.
 * Za jmeno doplni pobocku potvrzeneho PC podle profilu prihlaseni, nezavisle na MAIN a oblasti osoby.
 * Starsi prihlaseni bez zachovane vazby neslucuje odhadem podle user-agentu.
 */

(static function (): void {
    $rows = [];

    try {
        $conn = db();
        // Stejna hranice 06:00 jako u provozniho dne; pred sestou patri prihlaseni ke vcerejsku.
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $todayStart = $now->setTime(6, 0, 0);
        $dayStart = $now < $todayStart ? $todayStart->modify('-1 day') : $todayStart;
        $loginDayStart = $dayStart->format('Y-m-d H:i:s');
        $loginDayEnd = $dayStart->modify('+1 day')->format('Y-m-d H:i:s');
        require_once __DIR__ . '/../../common/lib/pc_session.php';
        require_once __DIR__ . '/../../common/lib/pobockove_pc.php';
        $onlineSince = $now->modify('-' . cb_pc_session_timeout_sec() . ' seconds')->format('Y-m-d H:i:s');
        // Zdroj polohy je potvrzeny profil PC daneho prihlaseni; bez pripravene tabulky zustane udaj prazdny.
        $pcBranchSelect = 'NULL AS pc_pobocka';
        $pcBranchJoin = '';
        if (cb_pobockove_pc_schema($conn)) {
            $pcBranchSelect = 'MAX(pc_branch.nazev) AS pc_pobocka';
            $pcBranchJoin = 'LEFT JOIN pobockove_pc branch_pc ON branch_pc.pc_hash=COALESCE(ul.pc_hash, ps.pc_hash) AND branch_pc.stav=1
                LEFT JOIN pobocka pc_branch ON pc_branch.id_pob=branch_pc.id_pob';
        }
        // Historie je zdrojem radku. Session doplnuje jen stav a jeste zachovanou starsi vazbu.
        $sql = "
            SELECT
                p.id_person AS id_user,
                " . cb_osoba_jmeno_sql('ou.id_person') . " AS cele_jmeno,
                (SELECT MIN(r.id_role) FROM hr_pristupovy_profil r WHERE r.id_person = p.id_person) AS id_role,
                MIN(ul.id_login) AS id_login,
                MIN(ul.kdy) AS login_time,
                MAX(spy.user_agent) AS user_agent,
                $pcBranchSelect,
                MAX(ua.cas) AS last_action,
                MAX(CASE WHEN ul.duvod = 2 AND ps.zruseno IS NULL
                    AND ps.last_seen >= ? THEN 1 ELSE 0 END) AS is_online
            FROM user_login ul
            INNER JOIN hr_person p ON p.id_person = ul.id_user AND p.aktivni = 1
            INNER JOIN " . cb_osoby_zdroj_sql() . " ou ON ou.id_person = p.id_person AND ou.platny = 1
            LEFT JOIN user_pc_session ps ON ps.id_login = ul.id_login AND ps.id_user = ul.id_user
            $pcBranchJoin
            LEFT JOIN user_spy spy ON spy.id_login = ul.id_login AND spy.id_user = ul.id_user
            LEFT JOIN user_akce_new ua ON ua.id_login = ul.id_login AND ua.id_user = ul.id_user
                AND ua.cas >= ? AND ua.cas < ?
            WHERE ul.akce = 1 AND ul.kdy >= ? AND ul.kdy < ?
            GROUP BY p.id_person, ou.jmeno, ou.prijmeni,
                COALESCE(HEX(ul.pc_hash), HEX(ps.pc_hash), CONCAT('login:', ul.id_login))
            ORDER BY COALESCE(MAX(ua.cas), MIN(ul.kdy)) DESC, cele_jmeno ASC, MIN(ul.id_login) DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nepodařilo se připravit přehled dnešních uživatelů.');
        }
        $stmt->bind_param('sssss', $onlineSince, $loginDayStart, $loginDayEnd, $loginDayStart, $loginDayEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $stmt->close();
    } catch (Throwable $e) {
        echo '<section class="blok"><h2 class="blok_title">Dnes aktivní uživatelé</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    // PC profily cislime podle jejich prvniho prihlaseni za den; dalsi prihlaseni cislo nemeni.
    $pcLogins = [];
    foreach ($rows as $row) {
        if (preg_match('/Android|iPhone/i', (string)($row['user_agent'] ?? '')) !== 1) {
            $pcLogins[(int)$row['id_user']][] = (int)$row['id_login'];
        }
    }
    $pcLabels = [];
    foreach ($pcLogins as $loginIds) {
        // Jedine pocitacove prihlaseni neni treba rozlisovat, ani pokud ma osoba take mobil.
        if (count($loginIds) < 2) {
            continue;
        }
        sort($loginIds, SORT_NUMERIC);
        foreach ($loginIds as $index => $loginId) {
            $pcLabels[$loginId] = 'PC' . ($index + 1);
        }
    }
    ?>
    <section class="blok provoz_aktivni_uzivatele">
        <h2 class="blok_title">Dnes aktivní uživatelé</h2>
        <table class="provoz_prehled_data">
            <thead><tr><th class="provoz_prehled_data_cell provoz_prehled_data_cell_left provoz_prehled_data_head">Uživatel</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Přihlášení</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Poslední akce</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Stav</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td class="provoz_prehled_data_cell provoz_prehled_data_cell_left" colspan="4">Žádní přihlášení uživatelé</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $name = trim((string)$row['cele_jmeno']);
                    // Pobocka patri konkretnimu profilu zarizeni, nikoliv pracovistim prihlaseneho cloveka.
                    $displayName = $name !== '' ? $name : ('ID ' . (string)$row['id_user']);
                    $pcBranchName = trim((string)($row['pc_pobocka'] ?? ''));
                    if ($pcBranchName !== '') {
                        $displayName .= ' (' . $pcBranchName . ')';
                    }
                    // Pri soubehu profilu ma vyssi role prednost; nebarvime podle prav prihlaseneho divaka.
                    $nameClass = match ((int)($row['id_role'] ?? 0)) {
                        1, 2 => 'provoz_aktivni_jmeno--cervena',
                        3, 4 => 'provoz_aktivni_jmeno--zelena',
                        5 => 'provoz_aktivni_jmeno--modra',
                        default => '',
                    };
                    $loginTime = trim((string)$row['login_time']);
                    $lastAction = trim((string)$row['last_action']);
                    // Rozpoznavame system konkretniho prihlaseni, nikoli zarizeni divaka panelu.
                    $userAgent = (string)($row['user_agent'] ?? '');
                    $mobileSystem = stripos($userAgent, 'Android') !== false ? 'Android'
                        : (stripos($userAgent, 'iPhone') !== false ? 'iPhone' : '');
                    ?>
                    <tr>
                        <td class="provoz_prehled_data_cell provoz_prehled_data_cell_left"><span class="<?= h($nameClass) ?>"><?= h($displayName) ?></span><?php if ($mobileSystem !== ''): ?> <span title="<?= h($mobileSystem) ?>" role="img" aria-label="<?= h('Mobil: ' . $mobileSystem) ?>">📱</span><?php elseif (isset($pcLabels[(int)$row['id_login']])): ?> <small title="Rozlišení přihlášení z počítače"><?= h($pcLabels[(int)$row['id_login']]) ?></small><?php endif; ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($loginTime !== '' ? date('G:i:s', strtotime($loginTime)) : '-') ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($lastAction !== '' ? date('G:i:s', strtotime($lastAction)) : '-') ?></td>
                        <td class="provoz_prehled_data_cell"><?= (int)$row['is_online'] === 1 ? 'online' : 'odhlášen' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
})();
