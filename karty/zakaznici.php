<?php
// karty/zakaznici.php * Verze: V8 * Aktualizace: 09.03.2026
declare(strict_types=1);

$totalZak = 0;
$activeZak = 0;
$blockedZak = 0;
$topLines = ['-', '-', '-'];

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $sqlCount = '
        SELECT
            COUNT(*) AS total_cnt,
            SUM(CASE WHEN blokovany = 0 THEN 1 ELSE 0 END) AS active_cnt,
            SUM(CASE WHEN blokovany = 1 THEN 1 ELSE 0 END) AS blocked_cnt
        FROM zakaznik
    ';
    $resCount = $conn->query($sqlCount);
    if ($resCount) {
        $row = $resCount->fetch_assoc() ?: [];
        $totalZak = (int)($row['total_cnt'] ?? 0);
        $activeZak = (int)($row['active_cnt'] ?? 0);
        $blockedZak = (int)($row['blocked_cnt'] ?? 0);
        $resCount->free();
    }

    $sqlTop = '
        SELECT
            COALESCE(z.jmeno, "") AS jmeno,
            COALESCE(z.prijmeni, "") AS prijmeni,
            COALESCE(z.mesto, "") AS mesto,
            COUNT(o.id_obj) AS obj_count
        FROM objednavka o
        INNER JOIN zakaznik z ON z.id_zak = o.id_zak
        GROUP BY z.id_zak, z.jmeno, z.prijmeni, z.mesto
        ORDER BY obj_count DESC, z.id_zak DESC
        LIMIT 3
    ';
    $resTop = $conn->query($sqlTop);
    if ($resTop) {
        $tmp = [];
        while ($r = $resTop->fetch_assoc()) {
            $jmeno = trim((string)($r['jmeno'] ?? ''));
            $prijmeni = trim((string)($r['prijmeni'] ?? ''));
            $mesto = trim((string)($r['mesto'] ?? ''));
            $obj = (int)($r['obj_count'] ?? 0);

            $fullName = trim($jmeno . ' ' . $prijmeni);
            if ($fullName === '') {
                $fullName = 'Neznámý zákazník';
            }
            if ($mesto === '') {
                $mesto = '-';
            }

            $tmp[] = $fullName . ' ' . $mesto . ' ' . $obj . ' obj.';
        }
        $resTop->free();

        for ($i = 0; $i < 3; $i++) {
            if (isset($tmp[$i])) {
                $topLines[$i] = $tmp[$i];
            }
        }
    }
} catch (Throwable $e) {
    // Karta ma zustat funkcni i pri vypadku DB.
    $totalZak = 0;
    $activeZak = 0;
    $blockedZak = 0;
    $topLines = ['-', '-', '-'];
}
?>

<article class="zak_card cb-zakaznici">
  <div class="card_top">
    <div>
      <h3 class="card_title">Seznam zákazníků</h3>
      <p class="card_subtitle">Souhrn zákaznické báze</p>
    </div>
    <div class="card_tools">
      <button
        type="button"
        class="card_tool_btn"
        data-zak-toggle="1"
        aria-expanded="false"
        title="Rozbalit/sbalit"
      >⤢</button>
    </div>
  </div>

  <div class="zak_compact" data-zak-compact>
    <p class="card_text">Nalezeno zákazníků: <strong><?= h((string)$totalZak) ?></strong></p>
    <p class="card_text">Aktivních / blokovaných: <strong><?= h((string)$activeZak) ?></strong> / <strong><?= h((string)$blockedZak) ?></strong></p>
    <p class="card_text">Nejaktivnější zákazníci:</p>
    <p class="card_text"><?= h($topLines[0]) ?></p>
    <p class="card_text"><?= h($topLines[1]) ?></p>
    <p class="card_text"><?= h($topLines[2]) ?></p>
  </div>

  <div class="zak_expanded is-hidden" data-zak-expanded>
    <p class="card_text card_text_muted">Maximalizovanou variantu karty připravíme v dalším kroku.</p>
  </div>
</article>

<?php
/* karty/zakaznici.php * Verze: V8 * Aktualizace: 09.03.2026 */
?>
