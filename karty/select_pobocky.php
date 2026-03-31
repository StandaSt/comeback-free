<?php
// karty/select_pobocky.php * Verze: V2 * Aktualizace: 25.03.2026
declare(strict_types=1);

$cbSelectError = '';
$cbAllowedPobocky = [];
$cbAllowedOblasti = [];
$cbAllowedIds = [];
$cbSelectedIds = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$cbSelectedMode = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
$cbSelectedOblast = trim((string)($_SESSION['selected_oblast'] ?? ''));
$cbSelectedOblasti = [];
$rawSelectedOblasti = $_SESSION['selected_oblasti'] ?? [];
if (is_array($rawSelectedOblasti)) {
    foreach ($rawSelectedOblasti as $o) {
        $o = trim((string)$o);
        if ($o !== '') {
            $cbSelectedOblasti[$o] = true;
        }
    }
}
if (!$cbSelectedOblasti && $cbSelectedOblast !== '') {
    $cbSelectedOblasti[$cbSelectedOblast] = true;
}
$cbSelectedOblasti = array_keys($cbSelectedOblasti);
sort($cbSelectedOblasti);

$cbUser = $_SESSION['cb_user'] ?? null;
$cbUserId = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

if ($cbUserId > 0) {
    try {
        $conn = db();
        $sql = '
            SELECT p.id_pob, p.nazev, p.oblast
            FROM user_pobocka up
            INNER JOIN pobocka p ON p.id_pob = up.id_pob
            WHERE up.id_user = ?
            ORDER BY p.nazev ASC
        ';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nepodařilo se připravit dotaz pro pobočky uživatele.');
        }

        $stmt->bind_param('i', $cbUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($r = $res->fetch_assoc()) {
                $id = (int)($r['id_pob'] ?? 0);
                $nazev = trim((string)($r['nazev'] ?? ''));
                $oblast = trim((string)($r['oblast'] ?? ''));
                if ($id <= 0 || $nazev === '') {
                    continue;
                }
                if ($oblast === '') {
                    $oblast = 'Nezarazeno';
                }

                $cbAllowedPobocky[] = [
                    'id_pob' => $id,
                    'nazev' => $nazev,
                    'oblast' => $oblast,
                ];
                $cbAllowedIds[$id] = true;
                $cbAllowedOblasti[$oblast] = true;
            }
            $res->close();
        }
        $stmt->close();
    } catch (Throwable $e) {
        $cbSelectError = $e->getMessage();
        $cbAllowedPobocky = [];
        $cbAllowedOblasti = [];
        $cbAllowedIds = [];
    }
}

$cbAllowedIdList = array_keys($cbAllowedIds);
sort($cbAllowedIdList);

$cbSelectedIdsClean = [];
foreach ($cbSelectedIds as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && isset($cbAllowedIds[$sid])) {
        $cbSelectedIdsClean[$sid] = true;
    }
}
$cbSelectedIds = array_keys($cbSelectedIdsClean);
sort($cbSelectedIds);

$cbAllowedAreaList = array_keys($cbAllowedOblasti);
sort($cbAllowedAreaList);
$cbAllowedNameMap = [];
foreach ($cbAllowedPobocky as $p) {
    $id = (int)($p['id_pob'] ?? 0);
    $nazev = trim((string)($p['nazev'] ?? ''));
    if ($id > 0 && $nazev !== '') {
        $cbAllowedNameMap[$id] = $nazev;
    }
}

$cbAllowedCount = count($cbAllowedPobocky);
$cbSelectedCount = count($cbSelectedIds);

$cbModeText = 'Bez výběru';
if ($cbSelectedMode === 'area' && $cbSelectedOblasti) {
    $cbModeText = 'Oblasti: ' . implode(', ', $cbSelectedOblasti);
} elseif ($cbSelectedMode === 'custom' && $cbSelectedCount > 0) {
    $names = [];
    foreach ($cbSelectedIds as $idSel) {
        $idSel = (int)$idSel;
        if (isset($cbAllowedNameMap[$idSel])) {
            $names[] = $cbAllowedNameMap[$idSel];
        }
    }
    $cbModeText = $names ? implode(', ', $names) : ('Pobočky: ' . $cbSelectedCount);
} elseif ($cbSelectedMode === 'single' && $cbSelectedCount === 1) {
    $idOne = (int)$cbSelectedIds[0];
    $cbModeText = $cbAllowedNameMap[$idOne] ?? 'Rychlá volba: 1 pobočka';
}

$card_min_html = ''
    . '<p class="card_text txt_seda odstup_vnejsi_0">Povolené pobočky: <strong>' . h((string)$cbAllowedCount) . '</strong></p>'
    . '<p class="card_text txt_seda odstup_vnejsi_0">Aktuální výběr: <strong>' . h($cbModeText) . '</strong></p>';

ob_start();
?>
<?php if ($cbSelectError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($cbSelectError) ?></p>
<?php elseif ($cbUserId <= 0): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted">Výběr pobočky je dostupný až po přihlášení.</p>
<?php elseif ($cbAllowedCount === 0): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted">Pro uživatele nejsou nastavené žádné pobočky.</p>
<?php elseif ($cbAllowedCount === 1): ?>
  <p class="card_text txt_seda odstup_vnejsi_0">Uživatel má jen jednu povolenou pobočku. Výběr se neprovádí, pobočka je nastavena automaticky.</p>
<?php else: ?>
  <div class="card_stack gap_10 displ_flex" id="cbSelectPobockyCard" data-save-url="<?= h(cb_url('index.php')) ?>">
    <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10">
      <h4 class="card_section_title txt_seda">Výběr podle oblasti</h4>
      <?php foreach ($cbAllowedAreaList as $oblast): ?>
        <?php
        $checked = ($cbSelectedMode === 'area' && in_array($oblast, $cbSelectedOblasti, true));
        ?>
        <label class="card_field gap_4 displ_flex">
          <span>
            <input type="checkbox" class="cb-pob-area" value="<?= h($oblast) ?>"<?= $checked ? ' checked' : '' ?>>
            <?= h($oblast) ?>
          </span>
        </label>
      <?php endforeach; ?>
    </section>

    <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10">
      <h4 class="card_section_title txt_seda">Výběr jednotlivých poboček</h4>
      <?php foreach ($cbAllowedPobocky as $p): ?>
        <?php
        $id = (int)$p['id_pob'];
        $checked = ($cbSelectedMode === 'custom' && in_array($id, $cbSelectedIds, true));
        ?>
        <label class="card_field gap_4 displ_flex">
          <span>
            <input type="checkbox" class="cb-pob-branch" value="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
            <?= h((string)$p['nazev']) ?> (<?= h((string)$p['oblast']) ?>)
          </span>
        </label>
      <?php endforeach; ?>
    </section>

    <p class="card_small_text radek_1_4">
      Oblasti a jednotlivé pobočky nelze kombinovat.
    </p>

    <div class="card_actions gap_8 displ_flex jc_konec">
      <button type="button" class="btn btn-primary" id="cbPobockySaveBtn">Uložit výběr</button>
    </div>
  </div>

<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/select_pobocky.php * Verze: V2 * Aktualizace: 25.03.2026 */
?>
