<?php
// funkce/zobraz_kartu.php * Verze: V1 * Aktualizace: 15.04.2026
declare(strict_types=1);


    function cb_zobraz_kartu(array $karta, bool $isNano, string $gridStyle = '', array $context = []): string
    {
        $fullPath = cb_dashboard_resolve_file((string)($karta['soubor'] ?? ''));
        $title = (string)($karta['nazev'] ?? '');
        $subtitleMin = (string)($karta['subtitle_min'] ?? '');
        $subtitleMax = (string)($karta['subtitle_max'] ?? '');
        $cardId = (int)($karta['id_karta'] ?? 0);
        $cardMode = $isNano ? 'nano' : 'mini';
        $cardPoradi = (int)($karta['poradi'] ?? 0);
        $renderPos = $isNano ? 0 : (int)($karta['__render_pos'] ?? 0);
        $renderCol = 0;
        $renderLine = 0;

        $userCardHeaderColorById = (array)($context['userCardHeaderColorById'] ?? []);
        $userCardIconFileById = (array)($context['userCardIconFileById'] ?? []);
        $userCardPosById = (array)($context['userCardPosById'] ?? []);
        $dashGridCols = (int)($context['dashGridCols'] ?? 3);

        if (!$isNano && $renderPos > 0) {
            $cols = ($dashGridCols > 0) ? $dashGridCols : 3;
            $renderCol = (($renderPos - 1) % $cols) + 1;
            $renderLine = (int)floor(($renderPos - 1) / $cols) + 1;
        }

        $cardLineHeightClass = $isNano ? ' radek_1_1' : ' radek_1_15';
        $cardTopStyle = '';
        if ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) {
            $cardTopStyle = 'background:' . (string)$userCardHeaderColorById[$cardId] . ';';
        }

        $cardIconFile = ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '';
        $hasCardIcon = ($cardIconFile !== '');
        $cardIconSrc = $hasCardIcon ? cb_url('/img/card_icons/' . ltrim($cardIconFile, '/')) : '';
        $cardColorUrl = cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId);
        $cardIconUrl = cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId);
        $storedPos = ($cardId > 0 && isset($userCardPosById[$cardId])) ? (array)$userCardPosById[$cardId] : ['col' => null, 'line' => null];
        $isPosLocked = (!$isNano && ($storedPos['col'] ?? null) !== null && ($storedPos['line'] ?? null) !== null);

        if ($isPosLocked) {
            $storedCol = (int)($storedPos['col'] ?? 0);
            $storedLine = (int)($storedPos['line'] ?? 0);
            if ($storedCol > 0 && $storedLine > 0) {
                $renderCol = $storedCol;
                $renderLine = $storedLine;
            }
        }

        $gridStyle = '';
        if ($renderCol > 0 && $renderLine > 0) {
            $gridStyle = 'grid-column:' . $renderCol . ';grid-row:' . $renderLine . ';';
        }

        $minRole = (int)($karta['min_role'] ?? 3);
        $cardTopRoleClass = '';
        if ($minRole === 1) {
            $cardTopRoleClass = ' card_top_role_1';
        } elseif ($minRole === 2) {
            $cardTopRoleClass = ' card_top_role_2';
        }

        $card_min_html = '';
        $card_max_html = '';
        $legacy_html = '';
        $startExpanded = false;

        if (!$isNano && $fullPath !== null) {
            ob_start();
            require $fullPath;
            $legacy_html = (string)ob_get_clean();
        }

        if ($card_min_html === '' && $card_max_html === '' && $legacy_html !== '') {
            $card_min_html = $legacy_html;
        }

        $cardClass = 'dash_card bg_bila card_blue zaobleni_12';
        if ($isNano) {
            $cardClass .= ' card_mode_nano';
        }

        ob_start();
        ?>
        <section class="<?= h($cardClass) ?>" data-cb-dash-card="1"<?= $gridStyle !== '' ? ' style="' . h($gridStyle) . '"' : '' ?>>
          <article class="card_shell<?= h($cardLineHeightClass) ?> odstup_vnitrni_0"
            data-card-id="<?= h((string)$cardId) ?>"
            data-card-mode="<?= h($cardMode) ?>"
            data-card-title="<?= h($title) ?>"
            data-card-col="<?= h((string)$renderCol) ?>"
            data-card-line="<?= h((string)$renderLine) ?>"
            data-card-pos-locked="<?= $isPosLocked ? '1' : '0' ?>"
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
                    <span class="card_pref_kid">K<?= h((string)$cardId) ?>/<?= h((string)$cardPoradi) ?></span>
                  </button>
                  <div class="card_pref_menu is-hidden" data-card-pref-menu="1">
                    <div class="card_pref_list" data-card-pref-list="1">
                      <button type="button" class="card_pref_item cursor_ruka" data-card-pref-open="color" data-card-pref-url="<?= h($cardColorUrl) ?>">Barva karty</button>
                      <button type="button" class="card_pref_item cursor_ruka" data-card-pref-open="ikon" data-card-pref-url="<?= h($cardIconUrl) ?>">Ikona karty</button>
                      <button type="button" class="card_pref_item cursor_ruka" data-card-pref-move="1">Přesunout na pozici</button>
                      <button type="button" class="card_pref_item cursor_ruka" data-card-pref-unlock-all="1">Odemkni vše</button>
                    </div>
                    <iframe class="card_pref_frame is-hidden" data-card-pref-frame="1" title="Nastavení karty"></iframe>
                  </div>
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
                  <button type="button" class="card_tool_btn only-mini cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-to-nano="1" title="Prepnout na nano">&bull;</button>
                <?php endif; ?>
              </div>
            </div>

            <div class="card_body odstup_vnejsi_0">
              <div class="card_min card_compact odstup_vnitrni_10" data-card-compact>
                <?= $card_min_html ?>
              </div>
              <?= cb_zobraz_karty_max($card_max_html) ?>
            </div>
          </article>
        </section>
        <?php
        return (string)ob_get_clean();
    }
