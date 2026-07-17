<?php
declare(strict_types=1);
?>
<header class="topbar">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <p class="page-subtitle">Personální agenda Pizza Comeback</p>
    </div>

    <div class="topbar-actions">
        <label class="search">
            <span aria-hidden="true">⌕</span>
            <input type="search" placeholder="Hledat zaměstnance, dokumenty, školení…">
        </label>

        <button class="icon-button" type="button" data-theme-toggle aria-label="Přepnout barevný režim">
            <span data-theme-icon>☾</span>
        </button>

    </div>
</header>
