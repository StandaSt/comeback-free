<?php
declare(strict_types=1);

(static function (): void {
    $rows = [];

    try {
        $conn = db();
        $timeout = function_exists('cb_pc_session_timeout_sec') ? cb_pc_session_timeout_sec() : 150;
        $sql = "
            SELECT
                u.id_user,
                TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS cele_jmeno,
                MIN(ps.vytvoreno) AS login_time,
                MAX(ps.last_seen) AS last_seen
            FROM user_pc_session ps
            INNER JOIN `user` u ON u.id_user = ps.id_user
            INNER JOIN hr_person p ON p.id_user = ps.id_user AND p.aktivni = 1
            INNER JOIN user_login ul ON ul.id_login = ps.id_login
                AND ul.id_user = ps.id_user
                AND ul.akce = 1
                AND ul.duvod = 2
            WHERE ps.zruseno IS NULL
              AND ps.last_seen >= (NOW() - INTERVAL " . (int)$timeout . " SECOND)
            GROUP BY u.id_user, u.jmeno, u.prijmeni
            ORDER BY last_seen DESC, cele_jmeno ASC
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
        echo '<section class="blok"><h2 class="blok_title">Přehled online uživatelů</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="blok">
        <h2 class="blok_title">Přehled online uživatelů</h2>
        <p class="provoz_prehled_meta"><?= h((string)count($rows)) ?> online</p>
        <table class="provoz_prehled_data">
            <thead><tr><th class="provoz_prehled_data_cell provoz_prehled_data_cell_left provoz_prehled_data_head">Uživatel</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Přihlášení</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Naposledy online</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td class="provoz_prehled_data_cell provoz_prehled_data_cell_left" colspan="3">Žádní online uživatelé</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $name = trim((string)$row['cele_jmeno']);
                    $loginTime = trim((string)$row['login_time']);
                    $lastSeen = trim((string)$row['last_seen']);
                    ?>
                    <tr>
                        <td class="provoz_prehled_data_cell provoz_prehled_data_cell_left"><?= h($name !== '' ? $name : ('ID ' . (string)$row['id_user'])) ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($loginTime !== '' ? date('G:i', strtotime($loginTime)) : '-') ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($lastSeen !== '' ? date('G:i:s', strtotime($lastSeen)) : '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
})();
