<?php
// K4
// karty/go_test.php * Verze: V9 * Aktualizace: 21.04.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Vyber a spuštění testovacích scriptů z admin_testy/ a vybraných podsložek.</p>';

const CB_GO_TEST_STATE_KEY = 'cb_go_test_state_v1';

if (!function_exists('cb_go_test_norm_name')) {
    function cb_go_test_norm_name(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name));
        if ($name === '') {
            return '';
        }

        if (!preg_match('~^[a-zA-Z0-9_\-/]+\.php$~', $name)) {
            return '';
        }

        $parts = explode('/', $name);
        $lastIndex = count($parts) - 1;

        foreach ($parts as $index => $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return '';
            }

            if ($index === $lastIndex) {
                if (!preg_match('~^[a-zA-Z0-9_\-]+\.php$~', $part)) {
                    return '';
                }
                continue;
            }

            if (!preg_match('~^[a-zA-Z0-9_\-]+$~', $part)) {
                return '';
            }
        }

        return implode('/', $parts);
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

        $scanDirs = [
            $dir,
            $dir . '/restia_testy',
            $dir . '/smeny_testy',
            $dir . '/reporty_google_testy',
        ];

        $files = [];
        foreach ($scanDirs as $scanDir) {
            if (!is_dir($scanDir)) {
                continue;
            }

            $items = scandir($scanDir);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_string($item) || $item === '.' || $item === '..' || substr($item, -4) !== '.php') {
                    continue;
                }

                $full = $scanDir . '/' . $item;
                if (!is_file($full)) {
                    continue;
                }

                $files[] = str_replace('\\', '/', substr($full, strlen($dir) + 1));
            }
        }

        natcasesort($files);
        return array_values($files);
    }
}

if (!function_exists('cb_go_test_resolve_full_path')) {
    function cb_go_test_resolve_full_path(string $relativePath): ?string
    {
        $baseDir = realpath(cb_go_test_admin_dir());
        if (!is_string($baseDir) || $baseDir === '') {
            return null;
        }

        $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $real = realpath($candidate);
        if (!is_string($real) || $real === '') {
            return null;
        }

        $basePrefix = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';
        $realNorm = str_replace('\\', '/', $real);
        if (strpos($realNorm, $basePrefix) !== 0 || !is_file($realNorm)) {
            return null;
        }

        return $realNorm;
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

        if (preg_match('~/\*\s*CO TENHLE SCRIPT\s*(.*?)\*/~isu', $raw, $m) === 1) {
            $block = trim((string)$m[1]);
            if ($block !== '') {
                $lines = preg_split('~\R~u', $block) ?: [];
                $out = [];
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    $line = preg_replace('~^\*+\s?~u', '', $line) ?: '';
                    $line = trim($line);
                    if ($line === '' || preg_match('~^=+$~u', $line) === 1) {
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
                    if ($line === '' || preg_match('~^=+$~u', $line) === 1) {
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

if (!function_exists('cb_go_test_state_default')) {
    function cb_go_test_state_default(): array
    {
        return [
            'script' => '',
            'step' => 'pick',
        ];
    }
}

if (!function_exists('cb_go_test_state_load')) {
    function cb_go_test_state_load(): array
    {
        $state = $_SESSION[CB_GO_TEST_STATE_KEY] ?? null;
        if (!is_array($state)) {
            return cb_go_test_state_default();
        }

        return [
            'script' => cb_go_test_norm_name((string)($state['script'] ?? '')),
            'step' => in_array((string)($state['step'] ?? 'pick'), ['pick', 'confirm', 'run'], true)
                ? (string)($state['step'] ?? 'pick')
                : 'pick',
        ];
    }
}

if (!function_exists('cb_go_test_state_save')) {
    function cb_go_test_state_save(array $state): void
    {
        $_SESSION[CB_GO_TEST_STATE_KEY] = [
            'script' => cb_go_test_norm_name((string)($state['script'] ?? '')),
            'step' => in_array((string)($state['step'] ?? 'pick'), ['pick', 'confirm', 'run'], true)
                ? (string)($state['step'] ?? 'pick')
                : 'pick',
        ];
    }
}

if (!function_exists('cb_go_test_state_clear')) {
    function cb_go_test_state_clear(): void
    {
        unset($_SESSION[CB_GO_TEST_STATE_KEY]);
    }
}

$gtFiles = cb_go_test_scan_files();
$gtRequest = array_merge($_GET, $_POST);
$gtAction = trim((string)($gtRequest['gt_action'] ?? ''));
if (!in_array($gtAction, ['select', 'run', 'reset'], true)) {
    $gtAction = '';
}
$gtRequestScript = cb_go_test_norm_name((string)($gtRequest['gt_script'] ?? ''));
$gtState = cb_go_test_state_load();

if ($gtAction === 'reset') {
    $gtState = cb_go_test_state_default();
    cb_go_test_state_clear();
} elseif ($gtAction === 'run' && $gtRequestScript !== '') {
    $gtState['script'] = $gtRequestScript;
    $gtState['step'] = 'run';
    cb_go_test_state_save($gtState);
} elseif ($gtAction === 'select' && $gtRequestScript !== '') {
    $gtState['script'] = $gtRequestScript;
    $gtState['step'] = 'confirm';
    cb_go_test_state_save($gtState);
}

$gtSelected = cb_go_test_norm_name((string)($gtState['script'] ?? ''));
$gtStep = (string)($gtState['step'] ?? 'pick');
$gtStep = in_array($gtStep, ['pick', 'confirm', 'run'], true) ? $gtStep : 'pick';

$gtSelectedFull = '';
$gtDescription = '';
$gtInfo = '';
$gtRunSrc = '';
$gtHasSelection = false;

if ($gtSelected !== '' && in_array($gtSelected, $gtFiles, true)) {
    $gtSelectedFull = cb_go_test_resolve_full_path($gtSelected) ?? '';
    if ($gtSelectedFull !== '') {
        $gtDescription = cb_go_test_extract_description($gtSelectedFull);
        $gtInfo = 'Vybraný script: admin_testy/' . $gtSelected;
        $gtRunSrc = cb_url('/admin_testy/' . $gtSelected);
        $gtHasSelection = true;
    }
}

$gtRootId = 'go_test_root_' . substr(md5(__FILE__), 0, 8);

ob_start();
?>
<div
  id="<?= h($gtRootId) ?>"
  class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10"
  style="width:100%; box-sizing:border-box;"
>
  <?php if ($gtFiles === []): ?>
    <p class="card_text txt_cervena odstup_vnejsi_0">Ve složce admin_testy/ a jejich podsložkách nejsou žádné PHP soubory.</p>
  <?php else: ?>

    <?php if ($gtStep === 'pick'): ?>
      <form method="post" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0" style="width:100%;" data-cb-max-form="1">
        <input type="hidden" name="page" value="go_test">
        <input type="hidden" name="gt_action" value="select">
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

    <?php elseif ($gtStep === 'confirm'): ?>
      <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10" data-go-test-detail="1" style="width:100%; max-width:720px; box-sizing:border-box;">
        <?php if ($gtHasSelection): ?>
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
        <?php else: ?>
          <p class="card_text txt_cervena odstup_vnejsi_0">Není vybraný platný script.</p>
        <?php endif; ?>

        <div class="card_actions gap_8 displ_flex jc_konec odstup_horni_10">
          <?php if ($gtHasSelection): ?>
            <form method="post" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
              <input type="hidden" name="page" value="go_test">
              <input type="hidden" name="gt_action" value="run">
              <input type="hidden" name="gt_script" value="<?= h($gtSelected) ?>">
              <button type="submit" class="btn btn-primary">Spustit</button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
            <input type="hidden" name="page" value="go_test">
            <input type="hidden" name="gt_action" value="reset">
            <button type="submit" class="btn btn-primary">Zpět</button>
          </form>
        </div>
      </div>

    <?php elseif ($gtStep === 'run'): ?>
      <div data-go-test-run="1">
        <?php if ($gtHasSelection && $gtRunSrc !== ''): ?>
          <p class="card_text txt_seda odstup_vnejsi_0"><?= h($gtInfo) ?></p>
          <iframe
            src="<?= h($gtRunSrc) ?>"
            title="Výstup test scriptu"
            sandbox="allow-scripts allow-same-origin allow-forms"
            style="width:100%; min-height:520px; border:1px solid var(--clr_seda_2); border-radius:10px; background:#fff;"
          ></iframe>
        <?php else: ?>
          <p class="card_text txt_cervena odstup_vnejsi_0">Není vybraný platný script.</p>
        <?php endif; ?>

        <div class="card_actions gap_8 displ_flex jc_konec odstup_horni_10">
          <form method="post" action="<?= h(cb_url('/')) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
            <input type="hidden" name="page" value="go_test">
            <input type="hidden" name="gt_action" value="reset">
            <button type="submit" class="btn btn-primary">Návrat</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/go_test.php * Verze: V9 * Aktualizace: 21.04.2026 */
// Konec souboru
?>
