<?php
declare(strict_types=1);

(static function (): void {
    $rows = [];

    try {
        $conn = db();
        $sql = "
            SELECT
                u.id_user,
                TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS cele_jmeno,
                ul.kdy AS login_time,
                COALESCE(ua.last_action_time, ul.kdy) AS last_action_time
            FROM `user` u
            INNER JOIN user_login ul ON ul.id_login = (
                SELECT MAX(ul2.id_login)
                FROM user_login ul2
                WHERE ul2.id_user = u.id_user
            )
            LEFT JOIN (
                SELECT id_login, MAX(cas) AS last_action_time
                FROM user_akce
                WHERE id_login IS NOT NULL
                GROUP BY id_login
            ) ua ON ua.id_login = ul.id_login
            CROSS JOIN (SELECT system_logout FROM set_system WHERE id_set = 1 LIMIT 1) ss
            WHERE ul.akce = 1
              AND ul.duvod = 2
              AND TIMESTAMPDIFF(MINUTE, COALESCE(ua.last_action_time, ul.kdy), NOW()) <= COALESCE(ss.system_logout, 20)
            ORDER BY last_action_time DESC, cele_jmeno ASC
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
            <thead><tr><th class="provoz_prehled_data_cell provoz_prehled_data_cell_left provoz_prehled_data_head">Uživatel</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Přihlášení</th><th class="provoz_prehled_data_cell provoz_prehled_data_head">Poslední akce</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td class="provoz_prehled_data_cell provoz_prehled_data_cell_left" colspan="3">Žádní online uživatelé</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $name = trim((string)$row['cele_jmeno']);
                    $loginTime = trim((string)$row['login_time']);
                    $lastActionTime = trim((string)$row['last_action_time']);
                    ?>
                    <tr>
                        <td class="provoz_prehled_data_cell provoz_prehled_data_cell_left"><?= h($name !== '' ? $name : ('ID ' . (string)$row['id_user'])) ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($loginTime !== '' ? date('G:i', strtotime($loginTime)) : '-') ?></td>
                        <td class="provoz_prehled_data_cell"><?= h($lastActionTime !== '' ? date('G:i', strtotime($lastActionTime)) : '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
})();
