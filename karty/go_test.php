<?php
// karty/go_test.php * Verze: V8 * Aktualizace: 02.04.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Výběr a spuštění testovacích scriptů z admin_testy/.</p>';

if (!function_exists('cb_go_test_norm_name')) {
    function cb_go_test_norm_name(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name));
        if ($name === '') {
            return '';
        }

        $name = basename($name);

        if (!preg_match('~^[a-zA-Z0-9_\-]+\.php$~', $name)) {
            return '';
        }

        return $name;
    }
}

if (!function_exists('cb_go_test_admin_dir')) {
    function cb_go_test_admin_dir(): string
    {
        return dirname(__DIR__) . '/admin_testy';
    }
}

if (!function_exists('cb_go_test_scan_files')) {
    function cb_go_test_scan_files(): array
    {
        $dir = cb_go_test_admin_dir();
        if (!is_dir($dir)) {
            return [];
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return [];
        }

        $files = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            if ($item === '.' || $item === '..') {
                continue;
            }

            if (substr($item, -4) !== '.php') {
                continue;
            }

            $full = $dir . '/' . $item;
            if (!is_file($full)) {
                continue;
            }

            $files[] = $item;
        }

        natcasesort($files);
        return array_values($files);
    }
}

if (!function_exists('cb_go_test_extract_description')) {
    function cb_go_test_extract_description(string $fullPath): string
    {
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            return 'Soubor nelze číst.';
        }

        $raw = file_get_contents($fullPath);
        if (!is_string($raw) || $raw === '') {
            return 'Soubor je prázdný nebo se nepodařilo načíst.';
        }

        if (preg_match('~/\*\s*CO TENHLE SCRIPT D[ĚE]L[ÁA]\s*(.*?)\*/~isu', $raw, $m) === 1) {
            $block = trim((string)$m[1]);
            if ($block !== '') {
                $lines = preg_split('~\R~u', $block) ?: [];
                $out = [];

                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    $line = preg_replace('~^\*+\s?~u', '', $line) ?: '';
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    if (preg_match('~^=+$~u', $line) === 1) {
                        continue;
                    }

                    $out[] = $line;
                }

                if ($out !== []) {
                    return implode("\n", $out);
                }
            }
        }

        if (preg_match('~/\*(.*?)\*/~isu', $raw, $m) === 1) {
            $block = trim((string)$m[1]);
            if ($block !== '') {
                $lines = preg_split('~\R~u', $block) ?: [];
                $out = [];

                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    $line = preg_replace('~^\*+\s?~u', '', $line) ?: '';
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    if (preg_match('~^=+$~u', $line) === 1) {
                        continue;
                    }

                    $out[] = $line;
                }

                if ($out !== []) {
                    return implode("\n", $out);
                }
            }
        }

        return 'Ve scriptu není popis v komentáři.';
    }
}

$gtFiles = cb_go_test_scan_files();
$gtSelected = cb_go_test_norm_name((string)($_GET['gt_script'] ?? ''));
$gtSelectedFull = '';
$gtDescription = '';
$gtInfo = '';
$gtHasSelection = false;
$gtRun = ((string)($_GET['gt_run'] ?? '') === '1');
$gtRootId = 'go_test_root_' . substr(md5(__FILE__), 0, 8);

if ($gtSelected !== '' && in_array($gtSelected, $gtFiles, true)) {
    $gtSelectedFull = cb_go_test_admin_dir() . '/' . $gtSelected;
    $gtDescription = cb_go_test_extract_description($gtSelectedFull);
    $gtHasSelection = true;
    $gtInfo = 'Vybraný script: admin_testy/' . $gtSelected;
}

ob_start();
?>
<div
  id="<?= h($gtRootId) ?>"
  class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10"
  style="width:100%; box-sizing:border-box;"
>
  <?php if ($gtFiles === []): ?>
    <p class="card_text txt_cervena odstup_vnejsi_0">Ve složce admin_testy/ nejsou žádné PHP soubory.</p>
  <?php else: ?>

    <?php if (!$gtRun): ?>
      <form method="get" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0" style="width:100%;">
        <input type="hidden" name="page" value="go_test">
        <div class="card_stack gap_10 displ_flex" style="width:100%;">
          <label class="card_field gap_4 displ_flex" style="width:100%;">
            <span>Testovací script</span>
            <select class="card_select ram_sedy txt_seda vyska_32" name="gt_script" style="width:100%; max-width:none;">
              <option value="">Vyber script</option>
              <?php foreach ($gtFiles as $file): ?>
                <option value="<?= h($file) ?>"<?= $gtSelected === $file ? ' selected' : '' ?>><?= h($file) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <div class="card_actions gap_8 displ_flex jc_konec">
            <button type="submit" class="btn btn-primary">Načíst</button>
          </div>
        </div>
      </form>

      <?php if ($gtHasSelection): ?>
        <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10 odstup_horni_10" data-go-test-detail="1" style="width:100%; max-width:720px; box-sizing:border-box;">
          <table class="table ram_normal bg_bila radek_1_35" style="width:100%; table-layout:fixed;">
            <tbody>
              <tr>
                <td class="text_tucny" style="width:180px;">Soubor</td>
                <td><?= h($gtInfo) ?></td>
              </tr>
              <tr>
                <td class="text_tucny">Popis</td>
                <td style="white-space:pre-wrap;"><?= h($gtDescription) ?></td>
              </tr>
            </tbody>
          </table>

          <div class="card_actions gap_8 displ_flex jc_konec odstup_horni_10">
            <form method="get" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0">
              <input type="hidden" name="page" value="go_test">
              <input type="hidden" name="gt_script" value="<?= h($gtSelected) ?>">
              <input type="hidden" name="gt_run" value="1">
              <button type="submit" class="btn btn-primary">Spustit</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div data-go-test-run="1">
      <?php include cb_go_test_admin_dir() . '/' . $gtSelected; ?>

      <div class="card_actions gap_8 displ_flex jc_konec odstup_horni_10">
        <form method="get" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="page" value="go_test">
          <button type="submit" class="btn btn-primary">Návrat</button>
        </form>
      </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/go_test.php * Verze: V8 * Aktualizace: 02.04.2026 */
// Počet řádků: 343
// Předchozí počet řádků: 347
// Konec souboru
?>
