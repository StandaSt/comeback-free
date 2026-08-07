<?php
declare(strict_types=1);

(static function (): void {
    $json = static function (array $payload): string {
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if (!is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Nepodařilo se připravit graf objednávek online.');
        }

        return $encoded;
    };

    $tz = new DateTimeZone('Europe/Prague');
    $now = new DateTimeImmutable('now', $tz);
    $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' 06:00:00', $tz);
    if (!($todayStart instanceof DateTimeImmutable)) {
        echo '<section class="provoz_prehled_block"><h2 class="provoz_prehled_title">Objednávky online</h2><p class="txt_cervena">Data se nepodařilo načíst.</p></section>';
        return;
    }

    $from = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;
    $to = $now->modify('+1 second');
    $cancelSql = "('canceled','rejected','expired','not_accepted','cancel_accepted')";

    $branches = [];
    $sumDokonceno = 0;
    $sumNaCeste = 0;
    $sumOsobniOdber = 0;
    $sumVyrabiSe = 0;
    $sumZruseno = 0;
    $sumObjednavky = 0;
    $sumTrzba = 0.0;
    $payloadJson = '';

    try {
        $conn = db();
        $conn->set_charset('utf8mb4');

        $branchSql = '
            SELECT p.id_pob, p.nazev, p.pob_color
            FROM pobocka p
            WHERE p.restia_activePosId IS NOT NULL
              AND p.restia_activePosId <> ""
            ORDER BY p.id_pob ASC
        ';
        $branchResult = $conn->query($branchSql);
        while ($branchResult instanceof mysqli_result && ($row = $branchResult->fetch_assoc())) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $name = trim((string)($row['nazev'] ?? ''));
            if ($idPob <= 0 || $name === '') {
                continue;
            }

            $branches[$idPob] = [
                'id_pob' => $idPob,
                'nazev' => $name,
                'barva' => trim((string)($row['pob_color'] ?? '')),
                'dokonceno' => 0,
                'na_ceste' => 0,
                'osobni_odber' => 0,
                'vyrabi_se' => 0,
                'zruseno' => 0,
                'objednavky' => 0,
                'trzba' => 0.0,
            ];
        }
        if ($branchResult instanceof mysqli_result) {
            $branchResult->free();
        }

        $summarySql = '
            SELECT
                o.id_pob,
                SUM(CASE WHEN COALESCE(st.nazev, \'\') NOT IN ' . $cancelSql . ' AND COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END) AS dokonceno,
                SUM(CASE WHEN COALESCE(st.nazev, \'\') NOT IN ' . $cancelSql . ' AND COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND (
                    EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM obj_sluzba s WHERE s.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'external-delivery\')
                ) THEN 1 ELSE 0 END) AS na_ceste,
                SUM(CASE WHEN COALESCE(st.nazev, \'\') NOT IN ' . $cancelSql . ' AND COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj) AND EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'pickup\') THEN 1 ELSE 0 END) AS osobni_odber,
                SUM(CASE WHEN COALESCE(st.nazev, \'\') NOT IN ' . $cancelSql . ' AND COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NULL THEN 1 ELSE 0 END) AS vyrabi_se,
                SUM(CASE WHEN COALESCE(st.nazev, \'\') IN ' . $cancelSql . ' THEN 1 ELSE 0 END) AS zruseno,
                SUM(CASE WHEN COALESCE(st.nazev, \'\') NOT IN ' . $cancelSql . ' THEN 1 ELSE 0 END) AS objednavky,
                SUM(COALESCE(c.cena_celk, 0)) AS trzba
            FROM obj_casy ca FORCE INDEX (ix_obj_casy_report_id)
            INNER JOIN objednavky_restia o ON o.id_obj = ca.id_obj
            LEFT JOIN cis_obj_stav st ON st.id_stav = o.id_stav
            LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
            WHERE ca.report >= DATE(?)
              AND ca.report < DATE(?)
              AND ca.cas_vytvor < ?
            GROUP BY o.id_pob
        ';
        $stmt = $conn->prepare($summarySql);
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit objednávky online.');
        }

        $fromDate = $from->format('Y-m-d');
        $toDate = $from->modify('+1 day')->format('Y-m-d');
        $toTime = $to->format('Y-m-d H:i:s');
        $stmt->bind_param('sss', $fromDate, $toDate, $toTime);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $idPob = (int)($row['id_pob'] ?? 0);
            if (!isset($branches[$idPob])) {
                continue;
            }

            $branches[$idPob]['dokonceno'] = (int)($row['dokonceno'] ?? 0);
            $branches[$idPob]['na_ceste'] = (int)($row['na_ceste'] ?? 0);
            $branches[$idPob]['osobni_odber'] = (int)($row['osobni_odber'] ?? 0);
            $branches[$idPob]['vyrabi_se'] = (int)($row['vyrabi_se'] ?? 0);
            $branches[$idPob]['zruseno'] = (int)($row['zruseno'] ?? 0);
            $branches[$idPob]['objednavky'] = (int)($row['objednavky'] ?? 0);
            $branches[$idPob]['trzba'] = (float)($row['trzba'] ?? 0);
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $stmt->close();

        $labels = [];
        $barvy = [];
        $dokoncenoData = [];
        $naCesteData = [];
        $osobniOdberData = [];
        $vyrabiSeData = [];
        $zrusenoData = [];
        $objednavkyData = [];
        $trzbaData = [];

        foreach ($branches as $branch) {
            $dokonceno = (int)$branch['dokonceno'];
            $naCeste = (int)$branch['na_ceste'];
            $osobniOdber = (int)$branch['osobni_odber'];
            $vyrabiSe = (int)$branch['vyrabi_se'];
            $zruseno = (int)$branch['zruseno'];
            $objednavky = (int)$branch['objednavky'];
            $trzba = (float)$branch['trzba'];

            $labels[] = (string)$branch['nazev'];
            $barvy[] = (string)$branch['barva'];
            $dokoncenoData[] = $dokonceno;
            $naCesteData[] = $naCeste;
            $osobniOdberData[] = $osobniOdber;
            $vyrabiSeData[] = $vyrabiSe;
            $zrusenoData[] = $zruseno;
            $objednavkyData[] = $objednavky;
            $trzbaData[] = $trzba;

            $sumDokonceno += $dokonceno;
            $sumNaCeste += $naCeste;
            $sumOsobniOdber += $osobniOdber;
            $sumVyrabiSe += $vyrabiSe;
            $sumZruseno += $zruseno;
            $sumObjednavky += $objednavky;
            $sumTrzba += $trzba;
        }

        $payloadJson = $json([
            'kind' => 'online_stavy',
            'labels' => $labels,
            'series' => [
                ['id' => 'dokonceno', 'name' => 'Dokončeno', 'data' => $dokoncenoData, 'colors' => $barvy],
                ['id' => 'na_ceste', 'name' => 'Na cestě', 'data' => $naCesteData],
                ['id' => 'osobni_odber', 'name' => 'Osobní odběr', 'data' => $osobniOdberData],
                ['id' => 'vyrabi_se', 'name' => 'Vyrábí se', 'data' => $vyrabiSeData],
                ['id' => 'zruseno', 'name' => 'Zrušeno', 'data' => $zrusenoData],
                ['id' => 'objednavky', 'name' => 'Objednávky', 'data' => $objednavkyData],
                ['id' => 'trzba', 'name' => 'Tržba', 'data' => $trzbaData],
            ],
        ]);
    } catch (Throwable $e) {
        echo '<section class="provoz_prehled_block"><h2 class="provoz_prehled_title">Objednávky online</h2><p class="txt_cervena">Online objednávky se nepodařilo načíst.</p></section>';
        return;
    }
    ?>
    <section class="provoz_prehled_block provoz_prehled_block_online">
        <h2 class="provoz_prehled_title">Objednávky online</h2>
        <div class="provoz_prehled_online_root" data-cb-prehledy-grafy="1">
            <script type="application/json" data-cb-prehledy-grafy-data><?= $payloadJson ?></script>

            <div class="provoz_prehled_online_summary" data-cb-tooltip-boundary="1">
                <span class="provoz_prehled_online_states">
                    <span><strong class="provoz_prehled_online_state_ok"><?= h((string)$sumDokonceno) ?></strong> OK</span>
                    <span><strong class="provoz_prehled_online_state_road"><?= h((string)$sumNaCeste) ?></strong> na cestě</span>
                    <span><strong class="provoz_prehled_online_state_pickup"><?= h((string)$sumOsobniOdber) ?></strong> os. odběr</span>
                    <span><strong class="provoz_prehled_online_state_work"><?= h((string)$sumVyrabiSe) ?></strong> vyrábí se</span>
                    <span><strong class="provoz_prehled_online_state_cancel"><?= h((string)$sumZruseno) ?></strong> zrušeno</span>
                </span>

                <span class="provoz_tooltip" tabindex="0" aria-label="Souhrn online objednávek" data-cb-tooltip-position="1">
                    <span>detail</span>
                    <span class="provoz_tooltip_panel provoz_tooltip_card" data-cb-tooltip-panel="1">
                        <span class="provoz_tooltip_title">Online objednávky podle poboček</span>
                        <table class="provoz_tooltip_table">
                            <thead>
                                <tr>
                                    <th>Pobočka</th>
                                    <th class="provoz_tooltip_num">Dok.</th>
                                    <th class="provoz_tooltip_num">Cesta</th>
                                    <th class="provoz_tooltip_num">Odběr</th>
                                    <th class="provoz_tooltip_num">Výroba</th>
                                    <th class="provoz_tooltip_num">Obj.</th>
                                    <th class="provoz_tooltip_num">Zruš.</th>
                                    <th class="provoz_tooltip_num">Tržba</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td><?= h((string)$branch['nazev']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h((string)(int)$branch['dokonceno']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h((string)(int)$branch['na_ceste']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h((string)(int)$branch['osobni_odber']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h((string)(int)$branch['vyrabi_se']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h((string)(int)$branch['objednavky']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h((string)(int)$branch['zruseno']) ?></td>
                                        <td class="provoz_tooltip_num"><?= h(number_format((float)$branch['trzba'], 0, ',', ' ')) ?> Kč</td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <th>Celkem</th>
                                    <th class="provoz_tooltip_num"><?= h((string)$sumDokonceno) ?></th>
                                    <th class="provoz_tooltip_num"><?= h((string)$sumNaCeste) ?></th>
                                    <th class="provoz_tooltip_num"><?= h((string)$sumOsobniOdber) ?></th>
                                    <th class="provoz_tooltip_num"><?= h((string)$sumVyrabiSe) ?></th>
                                    <th class="provoz_tooltip_num"><?= h((string)$sumObjednavky) ?></th>
                                    <th class="provoz_tooltip_num"><?= h((string)$sumZruseno) ?></th>
                                    <th class="provoz_tooltip_num"><?= h(number_format($sumTrzba, 0, ',', ' ')) ?> Kč</th>
                                </tr>
                            </tbody>
                        </table>
                    </span>
                </span>
            </div>

            <div id="prehled-objednavky-online-chart" class="provoz_prehled_online_chart" data-cb-prehledy-grafy-chart="1"></div>
        </div>
    </section>
    <?php
})();
