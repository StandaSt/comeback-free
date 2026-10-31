<?php
declare(strict_types=1);
/* Co-Link: komunikace s editorem a textovym tlacitkem Odeslat, kompaktnim hledanim, QR a vlastnim push odberem. */
if(!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="cs"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#123d45"><meta name="apple-mobile-web-app-capable" content="yes">
<title>Co-Link · Comeback</title>
<link rel="manifest" href="manifest.php"><link rel="icon" href="icon.php?size=192"><link rel="apple-touch-icon" href="icon.php?size=192">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">
<link rel="stylesheet" href="editor.css?v=<?= filemtime(__DIR__.'/editor.css') ?>">
</head><body>
<div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
<section id="welcome" class="welcome" hidden>
  <div class="welcome-card"><div class="brandmark" aria-hidden="true">Co</div><p class="eyebrow">COMEBACK · FIREMNÍ KOMUNIKACE</p>
  <h1>Co-Link</h1><p id="welcome-text">Vaši lidé. Vaše konverzace. Na jednom místě.</p>
  <p id="welcome-hint" class="hint">Připravuji vaše firemní přihlášení…</p></div>
</section>
<main id="app" class="app" hidden>
  <aside class="sidebar" aria-label="Konverzace">
    <header class="sidebar-head"><a class="brand" href="./">Co-Link<span>COMEBACK</span></a><button id="settings-open" class="icon-button" aria-label="Nastavení Co-Linku">⚙</button></header>
    <button id="directory-open" class="sidebar-action" aria-controls="messages"><span aria-hidden="true">▦</span> Adresář</button>
    <label class="sr-only" for="filter">Filtrovat konverzace</label><input id="filter" class="filter" type="search" placeholder="Najít konverzaci…" autocomplete="off">
    <div id="conversation-list" class="conversation-list"></div>
    <footer class="sidebar-foot"><span id="my-name"></span><span id="connection" class="connection">Připojuji…</span></footer>
  </aside>
  <section class="chat" aria-label="Otevřená konverzace">
    <header class="chat-head"><button id="back" class="icon-button mobile-only" aria-label="Zpět na konverzace">‹</button><div class="chat-heading"><h1 id="chat-name">Vítejte v Co-Linku</h1><p id="chat-subtitle">Prostor pro váš tým.</p></div>
      <button id="search-toggle" class="icon-button" type="button" aria-label="Hledat ve zprávách" title="Hledat ve zprávách" aria-controls="search-form" aria-expanded="false" hidden><svg class="chat-action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="m15 15 5 5"></path></svg></button>
      <button id="chat-info" class="icon-button" aria-label="Členové a informace" hidden>ⓘ</button>
      <button id="mute" class="icon-button" type="button" aria-label="Ztišit oznámení z této konverzace na všech vašich zařízeních" aria-pressed="false" hidden><svg class="chat-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path><path id="mute-slash" d="m3 3 18 18" visibility="hidden"></path></svg></button>
    </header>
    <form id="search-form" class="search-row" hidden><input id="search" type="search" placeholder="Hledat ve zprávách…" aria-label="Hledat ve zprávách"><button type="submit">Hledat</button><button type="button" id="clear-search">Zrušit</button></form>
    <div id="messages" class="messages" role="log" aria-label="Zprávy"></div>
    <div id="thread-banner" class="thread-banner" hidden><span></span><button id="thread-close" type="button">Zavřít vlákno ×</button></div>
    <div id="pending" class="pending" aria-live="polite"></div>
    <form id="composer" class="composer" hidden>
      <div id="staged-files" class="staged-files"></div>
      <div id="message-editor"></div>
      <div id="compose-actions" hidden><button id="attach" type="button" class="icon-button" aria-label="Přiložit soubor nebo fotografii">＋</button><button id="send-message" type="submit" class="send" aria-label="Odeslat zprávu">Odeslat</button></div>
      <input type="file" id="file" multiple hidden><input type="file" id="camera" accept="image/*" capture="environment" hidden>
      <div id="mention-list" class="mention-list" hidden></div><p class="compose-hint">Enter odešle · Shift + Enter nový řádek</p>
    </form>
  </section>
</main>
<template id="home-template">
  <section class="home" aria-label="Vítejte v Co-Linku">
    <div class="welcome-card">
      <div class="brandmark" aria-hidden="true">Co</div><p class="eyebrow">COMEBACK · FIREMNÍ KOMUNIKACE</p>
      <h1>Co-Link</h1><p>Vaši lidé. Vaše konverzace. Na jednom místě.</p>
      <p class="hint">Na telefonu načtěte tento osobní QR kód a připojte si Co-Link.</p>
      <div id="home-qr" class="qr" aria-label="Osobní QR kód pro připojení telefonu"></div>
      <p id="home-qr-status" class="qr-countdown" role="status">Připravuji osobní QR kód…</p>
    </div>
  </section>
</template>
<dialog id="panel"><form method="dialog" class="dialog-head"><h2 id="panel-title"></h2><button class="icon-button" aria-label="Zavřít">×</button></form><div id="panel-content"></div></dialog>
<script id="co-boot" type="application/json"><?= json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="../common/js/qrcode.min.js" defer></script>
<script src="message_format.js?v=<?= filemtime(__DIR__.'/message_format.js') ?>" defer></script>
<script src="emoji.js?v=<?= filemtime(__DIR__.'/emoji.js') ?>" defer></script>
<script src="editor.js?v=<?= filemtime(__DIR__.'/editor.js') ?>" defer></script>
<script src="installation.js?v=<?= filemtime(__DIR__.'/installation.js') ?>" defer></script>
<script src="push.js?v=<?= filemtime(__DIR__.'/push.js') ?>" defer></script>
<script src="auth_restore.js?v=<?= filemtime(__DIR__.'/auth_restore.js') ?>" defer></script>
<script src="app.js?v=<?= filemtime(__DIR__.'/app.js') ?>" defer></script>
</body></html>
