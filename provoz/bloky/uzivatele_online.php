<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zobrazit dnes aktivni uzivatele a cas jejich posledni skutecne
 * zaznamenane akce. Prihlasovaci den zacina po rannim odhlaseni v 08:00.
 * Technicky heartbeat zarizeni neni uzivatelska aktivita.
 * Jen v tomto bloku barvi jmena podle skutecne HR role zobrazovane osoby.
 * Kazde prihlaseni ma vlastni radek; Android a iPhone oznacuje ikonou mobilu.
 * Vice pocitacovych prihlaseni rozlisuje PC1, PC2; jedine PC neoznacuje.
 */

(static function (): void {
    $rows = [];

    try {
        $conn = db();
        // Hranice 08:00 odpovida dennimu odhlaseni; pred osmou jeste bezi predchozi prihlasovaci den.
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $todayStart = $now->setTime(8, 0, 0);
        $loginDayStart = ($now < $todayStart ? $todayStart->modify('-1 day') : $todayStart)
            ->format('Y-m-d H:i:s');
        $sql = "
            SELECT
                p.id_person AS id_user,
                TRIM(CONCAT_WS(' ', ou.jmeno, ou.prijmeni)) AS cele_jmeno,
                (SELECT MIN(r.id_role) FROM hr_pristupovy_profil r WHERE r.id_person = p.id_person) AS id_role,
                ps.id_login,
                ps.vytvoreno AS login_time,
                MAX(spy.user_agent) AS user_agent,
                MAX(ua.cas) AS last_action
            FROM user_pc_session ps
            INNER JOIN hr_person p ON p.id_person = ps.id_user AND p.aktivni = 1
            INNER JOIN hr_osobni_udaje ou ON ou.id_person = p.id_person AND ou.platny = 1
            INNER JOIN user_login ul ON ul.id_login = ps.id_login
                AND ul.id_user = ps.id_user
                AND ul.akce = 1
                AND ul.duvod = 2
            LEFT JOIN user_spy spy ON spy.id_login = ps.id_login AND spy.id_user = ps.id_user
            LEFT JOIN user_akce_new ua ON ua.id_login = ps.id_login AND ua.id_user = ps.id_user
            WHERE ps.zruseno IS NULL
                AND ps.vytvoreno >= ?
            GROUP BY p.id_person, ou.jmeno, ou.prijmeni, ps.id_login, ps.vytvoreno
            ORDER BY COALESCE(MAX(ua.cas), ps.vytvoreno) DESC, cele_jmeno ASC, ps.id_login DESC
            LIMIT 20
        ";
        $stmt = $conn->prepare($sql);
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nepodařilo se připravit přehled dnešních uživatelů.');
        }
        $stmt->bind_param('s', $loginDayStart);
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
    // Cisla PC jsou podle poradi prihlaseni, nikoli podle prubezne se menici posledni akce.
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
            <thead><tr><th class="provoz_prehled_data_cell provoz_prehled_data_cell_left provoz_prehled_data_head">Uživatel</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Přihlášení</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Poslední akce</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td class="provoz_prehled_data_cell provoz_prehled_data_cell_left" colspan="3">Žádní přihlášení uživatelé</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $name = trim((string)$row['cele_jmeno']);
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
                        <td class="provoz_prehled_data_cell provoz_prehled_data_cell_left"><span class="<?= h($nameClass) ?>"><?= h($name !== '' ? $name : ('ID ' . (string)$row['id_user'])) ?></span><?php if ($mobileSystem !== ''): ?> <span title="<?= h($mobileSystem) ?>" role="img" aria-label="<?= h('Mobil: ' . $mobileSystem) ?>">📱</span><?php elseif (isset($pcLabels[(int)$row['id_login']])): ?> <small title="Rozlišení přihlášení z počítače"><?= h($pcLabels[(int)$row['id_login']]) ?></small><?php endif; ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($loginTime !== '' ? date('G:i:s', strtotime($loginTime)) : '-') ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($lastAction !== '' ? date('G:i:s', strtotime($lastAction)) : '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
})();
