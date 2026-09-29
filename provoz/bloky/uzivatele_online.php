<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zobrazit prihlasene uzivatele a cas jejich posledni skutecne
 * zaznamenane akce. Technicky heartbeat zarizeni neni uzivatelska aktivita.
 */

(static function (): void {
    $rows = [];

    try {
        $conn = db();
        $sql = "
            SELECT
                p.id_person AS id_user,
                TRIM(CONCAT_WS(' ', ou.jmeno, ou.prijmeni)) AS cele_jmeno,
                MIN(ps.vytvoreno) AS login_time,
                MAX(ua.cas) AS last_action
            FROM user_pc_session ps
            INNER JOIN hr_person p ON p.id_person = ps.id_user AND p.aktivni = 1
            INNER JOIN hr_osobni_udaje ou ON ou.id_person = p.id_person AND ou.platny = 1
            INNER JOIN user_login ul ON ul.id_login = ps.id_login
                AND ul.id_user = ps.id_user
                AND ul.akce = 1
                AND ul.duvod = 2
            LEFT JOIN user_akce_new ua ON ua.id_login = ps.id_login AND ua.id_user = ps.id_user
            WHERE ps.zruseno IS NULL
            GROUP BY p.id_person, ou.jmeno, ou.prijmeni
            ORDER BY COALESCE(MAX(ua.cas), MIN(ps.vytvoreno)) DESC, cele_jmeno ASC
            LIMIT 20
        ";
        $result = $conn->query($sql);
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } catch (Throwable $e) {
        echo '<section class="blok"><h2 class="blok_title">Poslední přihlášení uživatelé</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="blok">
        <h2 class="blok_title">Poslední přihlášení uživatelé</h2>
        <table class="provoz_prehled_data">
            <thead><tr><th class="provoz_prehled_data_cell provoz_prehled_data_cell_left provoz_prehled_data_head">Uživatel</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Přihlášení</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Poslední akce</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td class="provoz_prehled_data_cell provoz_prehled_data_cell_left" colspan="3">Žádní přihlášení uživatelé</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $name = trim((string)$row['cele_jmeno']);
                    $loginTime = trim((string)$row['login_time']);
                    $lastAction = trim((string)$row['last_action']);
                    ?>
                    <tr>
                        <td class="provoz_prehled_data_cell provoz_prehled_data_cell_left"><?= h($name !== '' ? $name : ('ID ' . (string)$row['id_user'])) ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($loginTime !== '' ? date('G:i:s', strtotime($loginTime)) : '-') ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($lastAction !== '' ? date('G:i:s', strtotime($lastAction)) : '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
})();
