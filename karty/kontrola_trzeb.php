<?php
// K13
// karty/kontrola_trzeb.php * Verze: V3 * Aktualizace: 15.06.2026
// Zmena V3: max rezim ma denni tlacitka a jednoduchou tabulku po pobočkach z reporty_is a navaznych tabulek.
declare(strict_types=1);

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Kontrola tržeb není k dispozici.</p>';
$card_max_html = '';

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$selectedPob = [];
if (function_exists('get_selected_pobocky')) {
    $selectedPob = get_selected_pobocky();
}
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static function (int $value): bool {
    return $value > 0;
}));
if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob > 0) {
        $selectedPob = [$fallbackPob];
    }
}

$periodOdRaw = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
$periodDoRaw = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));

try {
    if ($periodOdRaw !== '') {
        $periodOd = new DateTimeImmutable($periodOdRaw);
    } else {
        $periodOd = new DateTimeImmutable('today 06:00:00');
    }
} catch (Throwable $e) {
    $periodOd = new DateTimeImmutable('today 06:00:00');
}

try {
    if ($periodDoRaw !== '') {
        $periodDo = new DateTimeImmutable($periodDoRaw);
    } else {
        $periodDo = new DateTimeImmutable('today 06:00:00');
    }
} catch (Throwable $e) {
    $periodDo = new DateTimeImmutable('today 06:00:00');
}

if ($periodDo < $periodOd) {
    $periodDo = $periodOd;
}

$dateOd = $periodOd->format('Y-m-d');
$dateDo = $periodDo->format('Y-m-d');
$periodLabel = $periodOd->format('j.n.Y G:i') . ' - ' . $periodDo->format('j.n.Y G:i');

$formatMoney = static function (float $value): string {
    return number_format($value, 0, ',', ' ') . ' Kč';
};

$formatNumber = static function (float $value, int $decimals): string {
    return number_format($value, $decimals, ',', ' ');
};

$formatCount = static function (int $value): string {
    return number_format($value, 0, ',', ' ');
};

$formatCol = static function (?float $value): string {
    if ($value === null) {
        return '-';
    }

    return number_format($value * 100, 1, ',', ' ') . ' %';
};

$formatDayLabel = static function (DateTimeImmutable $date): string {
    return $date->format('j.n.');
};

$zdrojText = static function (int $zdroj): string {
    if ($zdroj === 1) {
        return 'Google';
    }
    if ($zdroj === 2) {
        return 'IS';
    }

    return (string)$zdroj;
};

$whereSql = ' WHERE r.datum_reportu >= ? AND r.datum_reportu <= ?';
$whereTypes = 'ss';
$whereParams = [$dateOd, $dateDo];

if ($selectedPob !== []) {
    $whereSql .= ' AND r.id_pob IN (' . implode(',', array_fill(0, count($selectedPob), '?')) . ')';
    $whereTypes .= str_repeat('i', count($selectedPob));
    foreach ($selectedPob as $idPob) {
        $whereParams[] = $idPob;
    }
}

$sql = '
    SELECT
        r.id_reportu,
        r.datum_reportu,
        r.id_pob,
        COALESCE(p.nazev, "") AS pobocka,
        r.oteviral_text,
        r.zaviral_text,
        r.zdroj,
        r.platny,
        COALESCE(pk.hotovost, 0) AS hotovost,
        COALESCE(pk.terminal, 0) AS terminal,
        COALESCE(pk.stravenky, 0) AS stravenky,
        COALESCE(pk.rozdil, 0) AS rozdil,
        COALESCE(pk.vydaje_benzin, 0) AS vydaje_benzin,
        COALESCE(pk.vydaje_auta, 0) AS vydaje_auta,
        COALESCE(pk.vydaje_suroviny, 0) AS vydaje_suroviny,
        COALESCE(pk.vydaje_ostatni, 0) AS vydaje_ostatni,
        COALESCE(pk.vydaje_phm_soukrome, 0) AS vydaje_phm_soukrome,
        COALESCE(pk.vydaje_doklady_ks, 0) AS vydaje_doklady_ks,
        COALESCE(ri.trzba, 0) AS trzba,
        COALESCE(ri.wolt, 0) AS wolt,
        COALESCE(ri.bolt, 0) AS bolt,
        COALESCE(ri.damejidlo, 0) AS damejidlo,
        COALESCE(ri.web, 0) AS web,
        ri.col_pomer,
        COALESCE(ri.zrusene_obj_ks, 0) AS zrusene_obj_ks,
        COALESCE(ri.zrusene_obj_kc, 0) AS zrusene_obj_kc,
        COALESCE(ri.objednavky_nezrusene_ks, 0) AS objednavky_nezrusene_ks,
        COALESCE(o.pocet_osob, 0) AS pocet_osob,
        COALESCE(o.odpracovano, 0) AS odpracovano,
        COALESCE(o.rozvozu_celkem, 0) AS rozvozu_celkem
    FROM reporty_is r
    LEFT JOIN pobocka p
        ON p.id_pob = r.id_pob
    LEFT JOIN reporty_is_pokladna pk
        ON pk.id_reportu = r.id_reportu
    LEFT JOIN reporty_is_restia ri
        ON ri.id_reportu = r.id_reportu
    LEFT JOIN (
        SELECT
            id_reportu,
            COUNT(*) AS pocet_osob,
            SUM(odpracovano) AS odpracovano,
            SUM(rozvozu_celkem) AS rozvozu_celkem
        FROM reporty_is_osoby
        GROUP BY id_reportu
    ) o
        ON o.id_reportu = r.id_reportu
' . $whereSql . '
    ORDER BY r.datum_reportu DESC, p.nazev ASC, r.id_pob ASC
';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $card_min_html = '<p class="card_text txt_cervena odstup_vnejsi_0">Kontrolu tržeb se nepodařilo připravit.</p>';
    $card_max_html = $card_min_html;
    return;
}

$bindValues = [];
$bindValues[] = $whereTypes;
foreach ($whereParams as $key => $value) {
    $bindValues[] = &$whereParams[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bindValues);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$sumTrzba = 0.0;
$sumHotovost = 0.0;
$sumTerminal = 0.0;
$sumStravenky = 0.0;
$sumRozdil = 0.0;
$sumVydaje = 0.0;
$sumOdpracovano = 0.0;
$sumCol = 0.0;
$colCount = 0;
$problemCount = 0;
$stornoCount = 0;
$neplatneCount = 0;
$reportCount = 0;

if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $trzba = (float)$row['trzba'];
        $hotovost = (float)$row['hotovost'];
        $terminal = (float)$row['terminal'];
        $stravenky = (float)$row['stravenky'];
        $rozdil = (float)$row['rozdil'];
        $vydaje = (float)$row['vydaje_benzin'] + (float)$row['vydaje_auta'] + (float)$row['vydaje_suroviny'] + (float)$row['vydaje_ostatni'] + (float)$row['vydaje_phm_soukrome'];
        $odpracovano = (float)$row['odpracovano'];
        $colPomerRaw = $row['col_pomer'];
        $colPomer = null;
        if ($colPomerRaw !== null) {
            $colPomer = (float)$colPomerRaw;
        }

        $platny = (int)$row['platny'];
        $pocetOsob = (int)$row['pocet_osob'];
        $hasProblem = false;
        $problemText = 'OK';

        if ($platny !== 1) {
            $hasProblem = true;
            $problemText = 'neplatný';
            $neplatneCount++;
        } elseif (abs($rozdil) >= 1) {
            $hasProblem = true;
            $problemText = 'rozdíl pokladny';
        } elseif ($pocetOsob === 0) {
            $hasProblem = true;
            $problemText = 'chybí osoby';
        }

        if ($hasProblem) {
            $problemCount++;
        }

        $sumTrzba += $trzba;
        $sumHotovost += $hotovost;
        $sumTerminal += $terminal;
        $sumStravenky += $stravenky;
        $sumRozdil += $rozdil;
        $sumVydaje += $vydaje;
        $sumOdpracovano += $odpracovano;
        if ($colPomer !== null) {
            $sumCol += $colPomer;
            $colCount++;
        }
        $reportCount++;

        $rows[] = [
            'datum_reportu' => (string)$row['datum_reportu'],
            'pobocka' => trim((string)$row['pobocka']),
            'id_pob' => (int)$row['id_pob'],
            'zdroj' => (int)$row['zdroj'],
            'platny' => $platny,
            'oteviral_text' => trim((string)$row['oteviral_text']),
            'zaviral_text' => trim((string)$row['zaviral_text']),
            'trzba' => $trzba,
            'hotovost' => $hotovost,
            'terminal' => $terminal,
            'stravenky' => $stravenky,
            'rozdil' => $rozdil,
            'vydaje' => $vydaje,
            'vydaje_doklady_ks' => (int)$row['vydaje_doklady_ks'],
            'wolt' => (float)$row['wolt'],
            'bolt' => (float)$row['bolt'],
            'damejidlo' => (float)$row['damejidlo'],
            'web' => (float)$row['web'],
            'col_pomer' => $colPomer,
            'zrusene_obj_ks' => (int)$row['zrusene_obj_ks'],
            'objednavky_nezrusene_ks' => (int)$row['objednavky_nezrusene_ks'],
            'pocet_osob' => $pocetOsob,
            'odpracovano' => $odpracovano,
            'rozvozu_celkem' => (int)$row['rozvozu_celkem'],
            'problem' => $problemText,
            'has_problem' => $hasProblem,
        ];
    }
    $result->free();
}
$stmt->close();

$avgCol = null;
if ($colCount > 0) {
    $avgCol = $sumCol / $colCount;
}

$selectedDayRaw = trim((string)($_GET['ct_datum'] ?? ''));
$selectedDay = null;
try {
    if ($selectedDayRaw !== '') {
        $selectedDay = new DateTimeImmutable($selectedDayRaw);
    }
} catch (Throwable $e) {
    $selectedDay = null;
}
if (!($selectedDay instanceof DateTimeImmutable) || !preg_match('~^\d{4}-\d{2}-\d{2}$~', $selectedDay->format('Y-m-d'))) {
    $selectedDay = new DateTimeImmutable('yesterday');
}
$selectedDayDate = $selectedDay->format('Y-m-d');

$dayButtons = [];
for ($i = 1; $i <= 7; $i++) {
    $buttonDate = (new DateTimeImmutable('today'))->modify('-' . (string)$i . ' day');
    $dayButtons[] = [
        'value' => $buttonDate->format('Y-m-d'),
        'label' => $i === 1 ? 'Včera' : $formatDayLabel($buttonDate),
        'active' => $buttonDate->format('Y-m-d') === $selectedDayDate,
    ];
}

$dayRows = [];
$dayError = '';
try {
    $daySql = '
        SELECT
            p.id_pob,
            COALESCE(p.nazev, "") AS pobocka,
            COALESCE(SUM(COALESCE(ri.trzba, 0)), 0) AS trzba,
            COALESCE(SUM(COALESCE(pk.hotovost, 0)), 0) AS hotovost,
            COALESCE(SUM(COALESCE(pk.terminal, 0)), 0) AS terminal,
            COALESCE(SUM(COALESCE(pk.stravenky, 0)), 0) AS stravenky,
            COALESCE(SUM(COALESCE(pk.vydaje_benzin, 0) + COALESCE(pk.vydaje_auta, 0) + COALESCE(pk.vydaje_suroviny, 0) + COALESCE(pk.vydaje_ostatni, 0) + COALESCE(pk.vydaje_phm_soukrome, 0)), 0) AS vydaje,
            COALESCE(SUM(COALESCE(pk.rozdil, 0)), 0) AS rozdil,
            AVG(ri.col_pomer) AS col_pomer,
            COALESCE(SUM(COALESCE(o.pocet_osob, 0)), 0) AS pocet_osob,
            COALESCE(SUM(COALESCE(o.odpracovano, 0)), 0) AS odpracovano,
            COUNT(DISTINCT r.id_reportu) AS report_count
        FROM pobocka p
        LEFT JOIN reporty_is r
            ON r.id_pob = p.id_pob
           AND DATE(r.datum_reportu) = ?
        LEFT JOIN reporty_is_pokladna pk
            ON pk.id_reportu = r.id_reportu
        LEFT JOIN reporty_is_restia ri
            ON ri.id_reportu = r.id_reportu
        LEFT JOIN (
            SELECT
                id_reportu,
                COUNT(*) AS pocet_osob,
                SUM(odpracovano) AS odpracovano
            FROM reporty_is_osoby
            GROUP BY id_reportu
        ) o
            ON o.id_reportu = r.id_reportu
    ';
    $dayWhereSql = '';
    $dayWhereTypes = 's';
    $dayWhereParams = [$selectedDayDate];
    if ($selectedPob !== []) {
        $dayWhereSql = ' WHERE p.id_pob IN (' . implode(',', array_fill(0, count($selectedPob), '?')) . ')';
        $dayWhereTypes .= str_repeat('i', count($selectedPob));
        foreach ($selectedPob as $idPob) {
            $dayWhereParams[] = $idPob;
        }
    }
    $daySql .= $dayWhereSql . '
        GROUP BY p.id_pob, p.nazev
        ORDER BY p.nazev ASC, p.id_pob ASC
    ';

    $dayStmt = $conn->prepare($daySql);
    if ($dayStmt === false) {
        throw new RuntimeException('Nelze pripravit denni prehled.');
    }
    $dayBindValues = [];
    $dayBindValues[] = $dayWhereTypes;
    foreach ($dayWhereParams as $key => $value) {
        $dayBindValues[] = &$dayWhereParams[$key];
    }
    call_user_func_array([$dayStmt, 'bind_param'], $dayBindValues);
    $dayStmt->execute();
    $dayResult = $dayStmt->get_result();
    if ($dayResult instanceof mysqli_result) {
        while ($row = $dayResult->fetch_assoc()) {
            $dayRows[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'pobocka' => trim((string)($row['pobocka'] ?? '')),
                'trzba' => (float)($row['trzba'] ?? 0),
                'hotovost' => (float)($row['hotovost'] ?? 0),
                'terminal' => (float)($row['terminal'] ?? 0),
                'stravenky' => (float)($row['stravenky'] ?? 0),
                'vydaje' => (float)($row['vydaje'] ?? 0),
                'rozdil' => (float)($row['rozdil'] ?? 0),
                'col_pomer' => $row['col_pomer'] !== null ? (float)$row['col_pomer'] : null,
                'pocet_osob' => (int)($row['pocet_osob'] ?? 0),
                'odpracovano' => (float)($row['odpracovano'] ?? 0),
                'report_count' => (int)($row['report_count'] ?? 0),
            ];
        }
        $dayResult->free();
    }
    $dayStmt->close();
} catch (Throwable $e) {
    $dayRows = [];
    $dayError = 'Denni prehled se nepodarilo nacist.';
}

ob_start();
?>
<div class="sirka100 displ_flex flex_sloupec gap_8" style="height:100%; min-height:0;">
  <div class="displ_flex jc_mezi gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.2;">
    <span class="card_text txt_seda text_11"><?= h($periodLabel) ?></span>
    <span class="card_text text_11">
      <strong><?= h($formatCount($reportCount)) ?></strong> reportů,
      <strong><?= h($formatMoney($sumTrzba)) ?></strong>,
      <strong><?= h($formatCount($problemCount)) ?></strong> problémů
    </span>
  </div>
  <div class="displ_flex gap_8" style="flex-wrap:wrap;">
    <span class="card_text text_11 ram_sedy zaobleni_8 odstup_vnitrni_8 bg_bila">Rozdíl: <strong><?= h($formatMoney($sumRozdil)) ?></strong></span>
    <span class="card_text text_11 ram_sedy zaobleni_8 odstup_vnitrni_8 bg_bila">Výdaje: <strong><?= h($formatMoney($sumVydaje)) ?></strong></span>
    <span class="card_text text_11 ram_sedy zaobleni_8 odstup_vnitrni_8 bg_bila">COL: <strong><?= h($formatCol($avgCol)) ?></strong></span>
  </div>
</div>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<?php if ($dayError !== ''): ?>
  <p class="card_text txt_cervena odstup_vnejsi_0"><?= h($dayError) ?></p>
<?php else: ?>
  <form method="get" action="<?= h(cb_url('/')) ?>" class="card_stack gap_10 displ_flex" autocomplete="off" data-cb-max-form="1" data-cb-loader-text="Načítám kontrolu tržeb">
    <input type="hidden" name="cb_load_max" value="1">
    <input type="hidden" name="ct_datum" value="<?= h($selectedDayDate) ?>">

    <div class="displ_flex gap_8" style="flex-wrap:wrap; align-items:center;">
      <?php foreach ($dayButtons as $button): ?>
        <?php
        $isActive = !empty($button['active']);
        $buttonStyle = $isActive
            ? 'background:var(--clr_modra_2); border-color:var(--clr_modra_4); color:var(--clr_tmava_1); font-weight:700;'
            : 'background:var(--clr_bila); border-color:var(--clr_seda_2); color:var(--clr_cerna);';
        ?>
        <button
          type="submit"
          name="ct_datum"
          value="<?= h((string)$button['value']) ?>"
          class="cursor_ruka ram_normal zaobleni_8 text_11"
          style="padding:6px 10px; line-height:1.2; <?= h($buttonStyle) ?>"
          aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
        ><?= h((string)$button['label']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap ram_normal bg_bila zaobleni_12" style="max-height:100%; overflow:auto;">
      <table class="card-max-table" style="white-space:nowrap;">
        <thead>
          <tr>
            <th class="txt_l" style="width:180px;">Pobočka</th>
            <th class="txt_p" style="width:120px;">Tržba</th>
            <th class="txt_p" style="width:120px;">Hotovost</th>
            <th class="txt_p" style="width:120px;">Terminál</th>
            <th class="txt_p" style="width:120px;">Stravenky</th>
            <th class="txt_p" style="width:120px;">Výdaje</th>
            <th class="txt_p" style="width:120px;">Rozdíl</th>
            <th class="txt_p" style="width:100px;">Hodiny</th>
            <th class="txt_p" style="width:90px;">COL</th>
            <th class="txt_p" style="width:90px;">Osob</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($dayRows === []): ?>
            <tr>
              <td colspan="10" class="txt_c card_text" style="padding:16px 8px;">Pro vybraný den nejsou žádná data.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($dayRows as $row): ?>
              <?php
              $branchName = $row['pobocka'] !== '' ? $row['pobocka'] : 'Pobočka ' . (string)$row['id_pob'];
              ?>
              <tr>
                <td style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);"><?= h($branchName) ?></td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatMoney((float)$row['trzba'])) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatMoney((float)$row['hotovost'])) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatMoney((float)$row['terminal'])) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatMoney((float)$row['stravenky'])) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatMoney((float)$row['vydaje'])) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatMoney((float)$row['rozdil'])) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatNumber((float)$row['odpracovano'], 1)) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatCol($row['col_pomer'] !== null ? (float)$row['col_pomer'] : null)) ?>
                </td>
                <td class="txt_p" style="padding:5px 8px; border-bottom:1px solid var(--clr_seda_5);">
                  <?= h($formatCount((int)$row['pocet_osob'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
<?php endif; ?>
</div>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/kontrola_trzeb.php * Verze: V3 * Aktualizace: 15.06.2026 */
?>
