<?php
// karty/uzivatele.php * Verze: V14 * Aktualizace: 09.03.2026
declare(strict_types=1);
?>

<article class="card_shell cb-uzivatele">
  <div class="card_top">
    <div>
      <h3 class="card_title"><?= h((string)($cb_card_title ?? 'Seznam uživatelů')) ?></h3>
      <p class="card_subtitle"><span class="card_code"><?= h((string)($cb_card_code ?? '')) ?></span>Správa uživatelů IS Comeback</p>
    </div>
    <div class="card_tools">
      <button
        type="button"
        class="card_tool_btn"
        data-card-toggle="1"
        aria-expanded="false"
        title="Rozbalit/sbalit"
      >⤢</button>
    </div>
  </div>

  <div class="card_compact" data-card-compact>
    <p class="card_text">Zde bude přehled uživatelů.</p>
  </div>

  <div class="card_expanded is-hidden" data-card-expanded>
    <p class="card_text card_text_muted">Maximalizovanou variantu karty připravíme v dalším kroku.</p>
  </div>
</article>

<?php
/* karty/uzivatele.php * Verze: V14 * Aktualizace: 09.03.2026 */
?>
