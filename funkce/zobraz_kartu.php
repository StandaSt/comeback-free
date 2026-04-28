<?php
// funkce/zobraz_kartu.php * Verze: V4 * Aktualizace: 27.04.2026
declare(strict_types=1);

function cb_zobraz_kartu(array $pripravenaKarta): string
{
    $makeDiagnosticHtml = static function (string $soubor, string $mode, string $reason, array $details = []): string {
        $title = 'Chyba karty';
        $message = 'Max obsah se nepodařilo načíst.';
        if ($mode === 'nano') {
            $message = 'Obsah karty se nepodařilo načíst.';
        }

        $extra = [
            'Soubor' => $soubor !== '' ? $soubor : 'neznámý',
            'Očekávané' => 'card_max_html nebo legacy HTML output',
            'Selhání' => $reason,
        ];

        foreach ($details as $key => $value) {
            $text = trim((string)$value);
            if ($text === '') {
                continue;
            }
            $extra[(string)$key] = $text;
        }

        if (function_exists('cb_dashboard_render_card_error')) {
            return cb_dashboard_render_card_error($title, $message, $extra);
        }

        $escape = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $html = '<div class="odstup_vnitrni_0">';
        $html .= '<p class="card_text txt_cervena text_tucny odstup_vnejsi_0">' . $escape($title) . '</p>';
        $html .= '<p class="card_text txt_cervena odstup_vnejsi_0">' . $escape($message) . '</p>';
        foreach ($extra as $label => $value) {
            $html .= '<p class="card_text txt_seda odstup_vnejsi_0">' . $escape((string)$label . ': ' . (string)$value) . '</p>';
        }
        $html .= '</div>';

        return $html;
    };

    $mode = trim((string)($pripravenaKarta['mode'] ?? ''));
    if (!in_array($mode, ['mini', 'nano'], true)) {
        $mode = (isset($pripravenaKarta['col'], $pripravenaKarta['line'])
            && (int)$pripravenaKarta['col'] === 1
            && (int)$pripravenaKarta['line'] > 0
            && empty($pripravenaKarta['minHtml']))
            ? 'nano'
            : 'mini';
    }

    $isNano = ($mode === 'nano');
    $cardId = (int)($pripravenaKarta['cardId'] ?? 0);
    $cardPoradi = (int)($pripravenaKarta['cardPoradi'] ?? 0);
    $refreshOp = (int)($pripravenaKarta['refreshOp'] ?? 0);
    $title = (string)($pripravenaKarta['title'] ?? '');
    $role = (int)($pripravenaKarta['role'] ?? 3);
    $color = (string)($pripravenaKarta['color'] ?? '');
    $iconFile = (string)($pripravenaKarta['iconFile'] ?? '');
    $subtitleMin = !$isNano ? (string)($pripravenaKarta['subtitleMin'] ?? '') : '';
    $subtitleMax = !$isNano ? (string)($pripravenaKarta['subtitleMax'] ?? '') : '';

    if (!$isNano && $cardId === 19) {
        $subtitleMin = 'Aktualizace:';

        try {
            $conn = db();
            $resAktualizace = $conn->query('SELECT MAX(start) AS posledni_start FROM online_restia');
            if ($resAktualizace instanceof mysqli_result) {
                $rowAktualizace = $resAktualizace->fetch_assoc();
                $posledniStart = trim((string)($rowAktualizace['posledni_start'] ?? ''));
                $resAktualizace->free();

                if ($posledniStart !== '') {
                    $casAktualizace = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $posledniStart);
                    if ($casAktualizace instanceof DateTimeImmutable) {
                        $subtitleMin = 'Aktualizace: ' . $casAktualizace->format('H:i');
                    }
                }
            }
        } catch (Throwable $e) {
            $subtitleMin = 'Aktualizace:';
        }
    }
    $minHtml = (string)($pripravenaKarta['minHtml'] ?? '');
    $maxHtml = (string)($pripravenaKarta['maxHtml'] ?? '');
    $renderErrorHtml = (string)($pripravenaKarta['renderErrorHtml'] ?? '');
    $soubor = (string)($pripravenaKarta['soubor'] ?? '');
    $col = isset($pripravenaKarta['col']) ? (int)$pripravenaKarta['col'] : 0;
    $line = isset($pripravenaKarta['line']) ? (int)$pripravenaKarta['line'] : 0;
    $isPosLocked = !$isNano && ((int)($pripravenaKarta['isPosLocked'] ?? 0) === 1);
    $cardColorUrl = (string)($pripravenaKarta['cardColorUrl'] ?? '');
    $cardIconUrl = (string)($pripravenaKarta['cardIconUrl'] ?? '');
    $startExpanded = ((int)($pripravenaKarta['startExpanded'] ?? 0) === 1);
    $maxFill = ((int)($pripravenaKarta['maxFill'] ?? 0) === 1);
    $hasMaxLoaded = (!$isNano && trim($maxHtml) !== '');

    $hasCardIcon = ($iconFile !== '');
    $cardIconSrc = $hasCardIcon ? cb_url('/img/card_icons/' . ltrim($iconFile, '/')) : '';
    $cardTopStyle = $color !== '' ? 'background:' . h($color) . ';' : '';
    $cardTopRoleClass = ($role === 1) ? ' card_top_role_1' : (($role === 2) ? ' card_top_role_2' : '');
    $cardClass = 'dash_card bg_bila card_blue zaobleni_12' . ($isNano ? ' card_mode_nano' : '');
    $cardLineHeightClass = $isNano ? ' radek_1_1' : ' radek_1_15';

    if ($renderErrorHtml !== '') {
        if ($isNano && trim($minHtml) === '') {
            $minHtml = $renderErrorHtml;
        } elseif (!$isNano && trim($maxHtml) === '') {
            $maxHtml = $renderErrorHtml;
        }
    }

    if (!$isNano && trim($maxHtml) === '') {
        $reason = (trim($renderErrorHtml) !== '') ? 'prázdný max obsah po načtení' : 'prázdný výstup z render pipeline';
        $maxHtml = $makeDiagnosticHtml(
            $soubor,
            $mode,
            $reason,
            [
                'Požadovaný obsah' => 'max',
                'Chybějící data' => 'žádný HTML výstup',
            ]
        );
    }

    $gridStyle = '';
    if ($col > 0 && $line > 0) {
        $gridStyle = 'grid-column:' . $col . ';grid-row:' . $line . ';';
    }

    ob_start();
?>
<section class="<?= h($cardClass) ?>" data-cb-dash-card="1" data-card-refresh-op="<?= $refreshOp === 1 ? '1' : '0' ?>"<?= $gridStyle !== '' ? ' style="' . h($gridStyle) . '"' : '' ?>>
  <article class="card_shell<?= h($cardLineHeightClass) ?> odstup_vnitrni_0"
    data-card-id="<?= h((string)$cardId) ?>"
    data-card-poradi="<?= h((string)$cardPoradi) ?>"
    data-card-title="<?= h($title) ?>"
    data-card-mode="<?= h($mode) ?>"
    data-card-col="<?= h((string)$col) ?>"
    data-card-line="<?= h((string)$line) ?>"
    data-card-pos-locked="<?= $isPosLocked ? '1' : '0' ?>"
    data-card-max-loaded="<?= $hasMaxLoaded ? '1' : '0' ?>"
    <?= $maxFill ? ' data-card-max-fill="1"' : '' ?>
    <?= $startExpanded ? ' data-card-start-expanded="1"' : '' ?>>
    <div class="card_top<?= h($cardTopRoleClass) ?> gap_10 odstup_vnitrni_10 displ_flex jc_mezi"<?= $cardTopStyle !== '' ? ' style="' . h($cardTopStyle) . '"' : '' ?>>
      <div class="card_head_left displ_flex">
        <div class="card_pref_wrap<?= $isPosLocked ? ' card_pref_wrap_pos_locked' : '' ?>" data-card-pref-wrap="1">
          <button type="button" class="card_pref_toggle cursor_ruka bg_bila" data-card-pref-toggle="1" aria-haspopup="true" aria-expanded="false" title="Nastavení karty">
            <?php if ($hasCardIcon): ?>
              <span class="card_pref_icon"><img src="<?= h((string)$cardIconSrc) ?>" class="card_pref_icon_img" alt=""></span>
            <?php elseif ($isNano): ?>
              <span class="card_pref_empty" aria-hidden="true"></span>
            <?php else: ?>
              <span class="card_pref_dots txt_seda">&#8942;</span>
            <?php endif; ?>
          </button>
          <?php if (!$isNano): ?>
            <div class="card_pref_menu is-hidden" data-card-pref-menu="1">
              <?php require __DIR__ . '/../includes/card_menu_mini.php'; ?>
              <?php require __DIR__ . '/../includes/card_menu_max.php'; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="card_head_text">
          <h3 class="card_title txt_seda text_15 odstup_vnejsi_0"><?= h($title) ?></h3>
          <?php if (!$isNano): ?>
            <p
              class="card_subtitle text_12"
              data-card-subtitle="1"
              data-subtitle-min="<?= h($subtitleMin) ?>"
              data-subtitle-max="<?= h($subtitleMax) ?>"
            ><?= h($subtitleMin) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="card_tools gap_4 displ_flex flex_sloupec">
        <?php if ($isNano): ?>
          <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-nano-target="mini" title="Prepnout na mini">&#8722;</button>
        <?php else: ?>
          <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-toggle="1" aria-expanded="false" title="Prepnout na maxi/mini">&#10530;</button>
          <button type="button" class="card_tool_btn only-mini cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-to-nano="1" title="Prepnout na nano"><span class="nano_dot">&bull;</span></button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card_body odstup_vnejsi_0">
      <div class="card_min card_compact odstup_vnitrni_10" data-card-compact>
        <?= $minHtml ?>
      </div>
      <?php if (!$isNano): ?>
        <div class="card_max card_expanded odstup_vnitrni_10 is-hidden" data-card-expanded>
          <?= $maxHtml ?>
        </div>
      <?php endif; ?>
    </div>
  </article>
  <div class="dash_loader dash_card_loader is-hidden" data-card-loader="1" aria-hidden="true">
    <div class="dash_loader_inner">
      <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback" class="dash_loader_logo">
      <p class="dash_loader_text">Obnovuji data ...</p>
      <div class="dash_loader_time" data-cb-loader-time>0.00 s</div>
    </div>
  </div>
</section>
<?php
    return (string)ob_get_clean();
}
