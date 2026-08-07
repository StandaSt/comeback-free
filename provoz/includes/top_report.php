<?php
declare(strict_types=1);

(static function (): void {
    $tz = new DateTimeZone('Europe/Prague');
    $now = new DateTimeImmutable('now', $tz);
    $todayStart = new DateTimeImmutable($now->format('Y-m-d') . ' 06:00:00', $tz);
    $from = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;
    $to = $now->modify('+1 second');
    $rows = [];
    $totalOrders = 0;
    $totalSales = 0.0;

    try {
        $conn = db();
        $sql = "
            SELECT
                COALESCE(cp.nazev, 'Ostatní') AS kanal,
                COUNT(*) AS objednavky,
                SUM(COALESCE(c.cena_celk, 0)) AS trzba
            FROM objednavky_restia o
            LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
            LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
            LEFT JOIN cis_obj_stav st ON st.id_stav = o.id_stav
            WHERE o.restia_created_at >= ?
              AND o.restia_created_at < ?
              AND COALESCE(st.nazev, '') NOT IN ('canceled','rejected','expired','not_accepted','cancel_accepted')
            GROUP BY cp.nazev
            ORDER BY trzba DESC
            LIMIT 8
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit top report.');
        }
        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $to->format('Y-m-d H:i:s');
        $stmt->bind_param('ss', $fromSql, $toSql);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $row['objednavky'] = (int)$row['objednavky'];
            $row['trzba'] = (float)$row['trzba'];
            $totalOrders += $row['objednavky'];
            $totalSales += $row['trzba'];
            $rows[] = $row;
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $stmt->close();
    } catch (Throwable $e) {
        echo '<section class="provoz_prehled_block"><h2 class="provoz_prehled_title">Top report</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="provoz_prehled_block">
        <h2 class="provoz_prehled_title">Top report</h2>
        <p class="provoz_prehled_meta"><?= h($from->format('j.n.Y G:i')) ?> - <?= h($now->format('G:i')) ?></p>
        <table class="provoz_prehled_data">
            <thead><tr><th>Kanál</th><th>Obj.</th><th>Tržba</th><th>Podíl</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $share = $totalSales > 0 ? ((float)$row['trzba'] / $totalSales) * 100 : 0.0; ?>
                    <tr>
                        <td><?= h((string)$row['kanal']) ?></td>
                        <td><?= h((string)$row['objednavky']) ?></td>
                        <td><?= h(number_format((float)$row['trzba'], 0, ',', ' ')) ?></td>
                        <td><?= h(number_format($share, 1, ',', ' ')) ?> %</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Celkem</th><th><?= h((string)$totalOrders) ?></th><th><?= h(number_format($totalSales, 0, ',', ' ')) ?></th><th>100 %</th></tr></tfoot>
        </table>
    </section>
    <?php
})();
