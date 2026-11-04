<?php
declare(strict_types=1);
/* Kratky pruvodce oznamenimi: zjisti telefon, postupne ukazuje navody a test zobrazeni; kazde nacteni ohlasi spravci pres samostatny podepsany endpoint. Bez prihlaseni a primeho pristupu do DB. */

// Samostatna stranka nenacita bootstrap IS a nesmi menit ucet ani push subscription. Klic zustava pouze na serveru.
require_once __DIR__ . '/../config/secrets.php';
$openingId = bin2hex(random_bytes(16));
$openingTime = time();
$openingSignature = defined('CB_VAPID_PRIVATE') && (string)CB_VAPID_PRIVATE !== ''
    ? hash_hmac('sha256', 'cb-mobil-diag-open|' . $openingId . '|' . $openingTime, (string)CB_VAPID_PRIVATE)
    : '';
$nonce = base64_encode(random_bytes(24));
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce'; style-src 'nonce-$nonce'; img-src 'self'; connect-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback – nastavení oznámení</title>
  <style nonce="<?= $nonce ?>">
    /* Vzdy je videt jedina karta a pouze aktualni krok, vcetne navratu z nastaveni telefonu. */
    *{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172338;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
    main{max-width:440px;margin:0 auto;padding:24px 16px 32px}.brand{font-size:14px;font-weight:800;letter-spacing:.07em;color:#b52027;margin-bottom:20px}
    .device{margin:0 0 4px;font-size:20px;line-height:1.3;font-weight:700}.meta{margin:0;color:#526176;font-size:14px}.identity{margin-bottom:20px}
    .card{background:#fff;border:1px solid #dbe3ec;border-radius:16px;padding:22px}h1{font-size:25px;line-height:1.25;margin:0 0 16px}p{margin:0 0 16px}
    .step{color:#526176;font-size:13px;margin:0 0 10px}.hint{color:#526176;font-size:14px;margin-top:16px;margin-bottom:0}
    ol{padding-left:22px;margin:0 0 20px}li{padding:0 0 13px 3px}li:last-child{padding-bottom:0}
    button,.button{display:block;width:100%;border:0;border-radius:10px;padding:14px 12px;margin-top:12px;font:inherit;font-weight:650;text-align:center;text-decoration:none;background:#b52027;color:#fff;cursor:pointer;min-height:48px}
    .secondary{background:#eaf0f7;color:#213e60}.quiet{background:transparent;color:#526176;font-size:14px;margin-top:8px;min-height:44px}
    button:disabled{opacity:.55;cursor:wait}button:focus-visible,a:focus-visible,textarea:focus-visible{outline:3px solid #1573d1;outline-offset:3px}
    .message{border-left:3px solid #d0922c;padding-left:12px;font-size:14px}.report{width:100%;min-height:170px;padding:10px;font:13px/1.4 monospace;border:1px solid #aebccc;border-radius:8px;margin-top:14px}
    [hidden]{display:none!important}@media(max-width:360px){main{padding:18px 12px}.card{padding:18px}h1{font-size:23px}}
  </style>
</head>
<body>
<main>
  <div class="brand">COMEBACK</div>
  <div class="identity"><p class="device" id="device">Zjišťuji telefon…</p><p class="meta" id="deviceMeta"></p></div>
  <noscript><section class="card"><h1>Zapni JavaScript</h1><p>V Chromu otevři Nastavení → Nastavení webu → JavaScript. Povol ho a vrať se na tuto stránku.</p></section></noscript>
  <section class="card" aria-labelledby="title">
    <div id="content" role="status" aria-live="polite" aria-atomic="true">
      <p class="step" id="step">Kontrola telefonu</p><h1 id="title">Chvilku strpení</h1><p id="intro">Připravujeme návod pro tvůj telefon.</p>
      <p id="message" class="message" hidden></p><ol id="instructions" hidden></ol>
    </div>
    <button id="primary" hidden></button><button id="secondary" class="secondary" hidden></button>
    <div id="answers" hidden><button id="arrived">Přišla</button><button id="missing" class="secondary">Nepřišla</button></div>
    <a id="login" class="button" hidden>Otevřít IS Comeback</a>
    <button id="copy" class="secondary" hidden>Zkopírovat výsledek pro správce</button>
    <p id="copyStatus" class="hint" role="status"></p><textarea id="report" class="report" readonly aria-label="Výsledek kontroly" hidden></textarea>
    <button id="back" class="quiet" hidden>Zpět na návod</button><p id="hint" class="hint" hidden></p>
  </section>
</main>
<script nonce="<?= $nonce ?>">
/* Pruvodce cte jen dostupne udaje prohlizece; povoleni a test spousti az uzivatel klepnutim. */
(() => {
  'use strict';
  const el = id => document.getElementById(id);
  const root = new URL('../../', location.href);
  const storageKey = 'cb-mobil-diagnostika-v2';
  const opening = <?= json_encode(['id' => $openingId, 'time' => $openingTime, 'signature' => $openingSignature], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
  const ua = navigator.userAgent;
  const android = /Android/i.test(ua);
  const browser = /SamsungBrowser\//i.test(ua) ? 'Samsung Internet' : /EdgA\//i.test(ua) ? 'Edge' : /OPR\//i.test(ua) ? 'Opera' : /Chrome\//i.test(ua) ? 'Chrome' : 'prohlížeč';
  const supportedBrowser = android && ['Chrome', 'Samsung Internet'].includes(browser) && !/; wv\)/i.test(ua);
  let phone = android ? 'Telefon s Androidem' : 'Telefon';
  let model = '';
  let platformVersion = '';
  let view = 'loading';
  let guide = 0;
  let busy = false;
  let lastResult = 'neproveden';
  let notice = '';
  let primaryAction = null;
  let secondaryAction = null;
  let attempt = 0;
  el('login').href = root.href;

  /* Cekani na browser API je omezene, aby se uzivatel nezasekl na nacitani. */
  function limited(promise, ms = 6000) {
    let timer;
    return Promise.race([promise, new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('timeout')), ms); })]).finally(() => clearTimeout(timer));
  }

  /* Nazvy overeny na podpore Samsungu: SM-S921B=S24, SM-S926B=S24+, SM-S928B=S24 Ultra; ostatni modely neodhadujeme. */
  function phoneName(value) {
    const known = {'SM-S921B': 'Samsung Galaxy S24', 'SM-S926B': 'Samsung Galaxy S24+', 'SM-S928B': 'Samsung Galaxy S24 Ultra'};
    const key = value.toUpperCase().replace(/\/DS$/, '');
    return known[key] || (/^SM-/i.test(value) ? 'Samsung ' + value : value);
  }

  /* Client Hints umi vratit model i pri zkracenem UA; chybejici model ani One UI si nedomyslime. */
  async function identifyPhone() {
    try {
      if (navigator.userAgentData?.getHighEntropyValues) {
        const data = await limited(navigator.userAgentData.getHighEntropyValues(['model', 'platformVersion']), 2000);
        model = typeof data.model === 'string' ? data.model.trim().slice(0, 80) : '';
        platformVersion = typeof data.platformVersion === 'string' ? data.platformVersion.trim().slice(0, 30) : '';
      }
    } catch (_) { /* Bez Client Hints zustane bezpecny obecny popis telefonu. */ }
    if (!model) model = (ua.match(/\b(SM-[A-Z0-9-]+(?:\/DS)?)\b/i) || [])[1] || '';
    if (model) phone = phoneName(model);
    el('device').textContent = phone;
    el('deviceMeta').textContent = browser + (platformVersion ? ' · Android ' + platformVersion : '') + (!model ? ' · přesný model se nepodařilo zjistit' : '');
  }

  /* Kazde nacteni ma novy podpis a odesle jedno hlaseni; navigace pruvodcem tuto funkci nevola. */
  async function notifyOpening() {
    if (!opening.signature) return;
    try {
      await fetch(new URL('mobil_diagnostika_hlaseni.php', location.href).href, {
        method: 'POST', mode: 'same-origin', credentials: 'omit', keepalive: true,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({opening, phone, model, browser, android: platformVersion,
          permission: 'Notification' in window ? Notification.permission : 'unsupported'})
      });
    } catch (_) { /* Nedostupne hlaseni nesmi zastavit pomoc s oznamenimi na telefonu. */ }
  }

  /* Obnovime pouze kratky prubeh teto pomoci, aby kliknuti na testovaci oznameni neztratilo otazku. */
  function save() {
    try { sessionStorage.setItem(storageKey, JSON.stringify({view, guide, lastResult, attempt, time: Date.now()})); } catch (_) { /* Soukromy rezim muze uloziste zakazat. */ }
  }

  /* Povolenou navigaci omezujeme na zname stavy; uloziste nikdy nepotvrzuje prihlaseni ani prijem push. */
  function restore() {
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
      if (!saved || !Number.isFinite(saved.time) || Date.now() - saved.time > 30 * 60 * 1000 || saved.time > Date.now()) return false;
      if (!['guide', 'permission', 'question', 'success', 'help'].includes(saved.view)) return false;
      view = saved.view; guide = Number.isInteger(saved.guide) ? Math.max(0, Math.min(2, saved.guide)) : 0;
      attempt = Number.isInteger(saved.attempt) ? Math.max(0, Math.min(1000, saved.attempt)) : 0;
      lastResult = ['neproveden', 'čeká na odpověď', 'uživatel potvrdil zobrazení', 'uživatel oznámení neviděl', 'test se nespustil', 'oprávnění je zakázané'].includes(saved.lastResult) ? saved.lastResult : 'neproveden';
      return true;
    } catch (_) { return false; }
  }

  /* Jednotlive navody se nikdy nezobrazuji soucasne; po neuspechu prejdeme na dalsi moznost. */
  function guideContent() {
    const site = location.hostname;
    if (guide === 0) return {
      title: 'Povol oznámení pro Comeback',
      intro: 'Nejdřív zkontroluj povolení tohoto webu.',
      steps: browser === 'Samsung Internet' ? [
        'V telefonu otevři Nastavení → Oznámení → Oznámení aplikací.',
        'Vyber Samsung Internet a v seznamu webů povol ' + site + '.',
        'Vrať se sem a klepni na Test oznámení.'
      ] : [
        'Vedle adresy webu klepni na ikonu ovládání webu.',
        'Otevři Oprávnění → Oznámení a povol je.',
        'Vrať se sem a klepni na Test oznámení.'
      ]
    };
    if (guide === 1) return {
      title: 'Povol oznámení aplikace ' + browser,
      intro: 'Teď zkontroluj nastavení telefonu.',
      steps: [
        'Na ploše nebo v seznamu aplikací podrž ikonu ' + browser + '.',
        'Klepni na ⓘ → Oznámení a zapni Povolit oznámení.',
        'Vrať se sem a klepni na Test oznámení.'
      ]
    };
    return {
      title: 'Zkontroluj další blokaci',
      intro: /^SM-/i.test(model) ? 'Ještě jeden postup pro Samsung.' : 'Pokud máš Samsung, zkontroluj také kategorie oznámení.',
      steps: [
        'V telefonu otevři Nastavení → Oznámení → Rozšířená nastavení. Zapni Správu kategorií oznámení u aplikací, pokud je tato volba dostupná.',
        'Vrať se do nastavení aplikace ' + browser + ' → Oznámení → Kategorie oznámení. Povol oznámení tohoto webu, pokud ho v seznamu vidíš.',
        'Vypni režim Nerušit, vrať se sem a zopakuj test.'
      ]
    };
  }

  /* Prekresleni nahradi obsah jedine karty; technicke detaily se ukazuji az pri zadosti o pomoc. */
  function render() {
    for (const id of ['primary', 'secondary', 'answers', 'login', 'copy', 'report', 'back', 'hint', 'instructions', 'message']) el(id).hidden = true;
    el('instructions').replaceChildren(); el('copyStatus').textContent = '';
    el('intro').hidden = false; primaryAction = null; secondaryAction = null;
    el('step').textContent = '';
    let title = ''; let intro = '';
    if (view === 'guide') {
      const content = guideContent(); title = content.title; intro = content.intro;
      el('step').textContent = 'Návod ' + (guide + 1) + ' ze 3';
      for (const text of content.steps) { const item = document.createElement('li'); item.textContent = text; el('instructions').append(item); }
      el('instructions').hidden = false;
      setPrimary('Test oznámení', test);
      setSecondary('Tuhle volbu nemám', nextGuide);
    } else if (view === 'permission') {
      title = 'Povol oznámení'; intro = 'Klepni na tlačítko. Až se Chrome zeptá, zvol Povolit.';
      if (browser !== 'Chrome') intro = 'Klepni na tlačítko a v dotazu prohlížeče zvol Povolit.';
      setPrimary('Povolit a vyzkoušet', requestPermission);
      setSecondary('Ukázat nastavení', () => { guide = 0; go('guide'); });
    } else if (view === 'running') {
      title = 'Spouštím test…'; intro = 'Chvilku počkej.';
    } else if (view === 'question') {
      title = 'Přišla zkušební notifikace?'; intro = 'Stáhni horní lištu telefonu. Hledej „Comeback – zkouška oznámení“.';
      el('answers').hidden = false;
    } else if (view === 'success') {
      title = 'Oznámení se zobrazilo'; intro = 'Teď zkus běžné přihlášení do IS.';
      el('login').hidden = false;
      el('hint').textContent = 'Pokud potvrzení přihlášení nepřijde, použij QR a pošli správci výsledek kontroly.'; el('hint').hidden = false;
      el('copy').hidden = false;
    } else if (view === 'help') {
      title = 'Předáme výsledek správci'; intro = 'Zkopíruj výsledek a pošli ho správci v SMS. Do IS se zatím přihlas přes QR.';
      el('copy').hidden = false; setSecondary('Zkusit test znovu', test); el('back').hidden = false;
    } else {
      title = 'Tady test nefunguje';
      intro = !window.isSecureContext ? 'Požádej správce o zabezpečený odkaz HTTPS.' : !supportedBrowser ? 'Otevři odkaz v běžném Chromu nebo Samsung Internetu na telefonu s Androidem.' : 'Zkus aktualizovat prohlížeč a použít běžné okno místo anonymního.';
      el('copy').hidden = false;
    }
    el('title').textContent = title; el('intro').textContent = intro;
    if (notice) { el('message').textContent = notice; el('message').hidden = false; }
    save();
  }

  /* Akce jsou vazane na aktualni obrazovku a nelze je spustit soubezne. */
  function setPrimary(label, action) { el('primary').textContent = label; el('primary').hidden = false; primaryAction = action; }
  function setSecondary(label, action) { el('secondary').textContent = label; el('secondary').hidden = false; secondaryAction = action; }
  function go(next) { view = next; render(); window.scrollTo({top: 0, behavior: 'instant'}); }
  function nextGuide() { notice = ''; if (guide < 2) { guide++; go('guide'); } else go('help'); }

  /* Test pouziva pouze presny korenovy worker IS, nikdy worker Co-Linku. */
  async function registration() {
    const found = await limited(navigator.serviceWorker.getRegistration(root.href));
    if (found && found.scope === root.href) return found;
    return limited(navigator.serviceWorker.register(new URL('sw.js', root).href, {scope: root.href}));
  }

  /* API pro povoleni musi byt vyvolano primo z klepnuti, jeste pred jakymkoli cekanim. */
  async function requestPermission() {
    if (busy) return;
    busy = true; el('primary').disabled = true; el('secondary').disabled = true;
    let result = 'default';
    try { result = await Notification.requestPermission(); } catch (_) { /* Pri chybe dostane uzivatel rucni navod. */ }
    finally { busy = false; el('primary').disabled = false; el('secondary').disabled = false; }
    if (result === 'granted') await test();
    else { guide = 0; notice = 'Povolení se nepodařilo získat. Zkus ho zapnout ručně.'; go('guide'); }
  }

  /* showNotification overuje mistni zobrazeni, nikoli doruceni serveroveho push; uspech potvrdi az clovek. */
  async function test() {
    if (busy) return;
    notice = '';
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !window.isSecureContext) { go('unavailable'); return; }
    if (Notification.permission === 'default') { go('permission'); return; }
    if (Notification.permission !== 'granted') {
      lastResult = 'oprávnění je zakázané';
      if (guide < 2) { guide++; notice = 'Oznámení jsou stále blokovaná. Zkus tento postup.'; go('guide'); }
      else go('help');
      return;
    }
    busy = true; go('running'); attempt++;
    try {
      const reg = await registration();
      const deadline = Date.now() + 6000;
      while (!reg.active && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 100));
      if (!reg.active) throw new Error('worker-not-active');
      await limited(reg.showNotification('Comeback – zkouška oznámení', {
        body: 'Zkouška zobrazení. Vrať se na stránku a zvol Přišla nebo Nepřišla.',
        icon: new URL('common/img/logo_comeback.png', root).href,
        tag: 'cb-mobil-diagnostika', renotify: true, data: {url: location.href}
      }));
      lastResult = 'čeká na odpověď'; go('question');
    } catch (_) {
      lastResult = 'test se nespustil';
      if (guide < 2) { guide++; notice = 'Test se nepodařilo spustit. Zkus ještě tento postup.'; go('guide'); }
      else go('help');
    } finally { busy = false; }
  }

  /* Zprava obsahuje jen popis prostredi a vysledku, nikdy endpoint ani klice odberu. */
  function reportText() {
    const permission = 'Notification' in window ? Notification.permission : 'nepodporováno';
    return ['Comeback – kontrola oznámení', 'Telefon: ' + phone, 'Model: ' + (model || 'nezjištěn'), 'Prohlížeč: ' + browser,
      'Android: ' + (platformVersion || 'verze nezjištěna'), 'Povolení webu: ' + permission, 'Poslední návod: ' + (guide + 1),
      'Počet testů: ' + attempt, 'Místní test: ' + lastResult, 'Čas: ' + new Date().toLocaleString('cs-CZ'),
      'Serverové doručení: tímto testem se neověřuje'].join('\n');
  }

  el('primary').addEventListener('click', () => { if (!busy && primaryAction) primaryAction(); });
  el('secondary').addEventListener('click', () => { if (!busy && secondaryAction) secondaryAction(); });
  el('arrived').addEventListener('click', () => { if (!busy && view === 'question') { lastResult = 'uživatel potvrdil zobrazení'; notice = ''; go('success'); } });
  el('missing').addEventListener('click', () => { if (!busy && view === 'question') { lastResult = 'uživatel oznámení neviděl'; nextGuide(); } });
  el('back').addEventListener('click', () => { notice = ''; go('guide'); });
  // Rucni sdileni vysledku ma srozumitelnou nahradu, pokud prohlizec nepovoli schranku.
  el('copy').addEventListener('click', async () => {
    const text = reportText();
    try { await navigator.clipboard.writeText(text); el('copyStatus').textContent = 'Zkopírováno. Vlož výsledek do SMS správci.'; }
    catch (_) { el('report').value = text; el('report').hidden = false; el('report').focus(); el('report').select(); el('copyStatus').textContent = 'Podrž označený text a zvol Kopírovat.'; }
  });

  /* Identifikace se provede jednou; navrat z nastaveni nesmi preskocit test ani odpoved uzivatele. */
  async function start() {
    await identifyPhone();
    // Hlasime i jiny nebo nepodporovany prohlizec, vzdy pred rozdelenim jednotlivych navodu.
    void notifyOpening();
    if (!supportedBrowser || !window.isSecureContext || !('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) { view = 'unavailable'; render(); return; }
    if (!restore()) { view = Notification.permission === 'default' ? 'permission' : 'guide'; guide = Notification.permission === 'granted' ? 1 : 0; }
    // Drive potvrzeny uspech se po odvolani opravneni nesmi vydavat za aktualne funkcni stav.
    if (['success', 'question'].includes(view) && Notification.permission !== 'granted') { view = 'guide'; guide = 0; lastResult = 'oprávnění je zakázané'; }
    render();
  }
  start();
})();
</script>
</body>
</html>
