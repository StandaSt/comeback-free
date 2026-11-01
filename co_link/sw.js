/* Co-Link: staticke prostredi, obnova prihlaseni a push s odesilatelem a uryvkem; soukrome odpovedi API se necachuji. */
const VERSION = 'co-link-v15';
// Klient, editor i formatovani maji prefix co_; puvodni adresy zustavaji v cache pro otevrene starsi klienty.
const STYLE_FILES = ['co_style/co_zaklad.css','co_style/co_rozlozeni.css','co_style/co_navigace.css','co_style/co_zpravy.css','co_style/co_psani.css','co_style/co_uvitani.css','co_style/co_dialogy.css','co_style/co_adresar.css','co_style/co_is.css'];
// Vsechny casti klienta musi byt dostupne i pri startu bez site.
const APP_FILES = ["co_js/co_rozhrani.js","co_js/co_zpravy.js","co_js/co_psani.js","co_js/co_adresar.js","co_js/co_sprava_konverzaci.js","co_js/co_nastaveni.js"];
const ROOT = new URL('./', self.location.href);
self.addEventListener('install', event => {
  event.waitUntil(caches.open(VERSION).then(cache => cache.addAll([...STYLE_FILES,...APP_FILES,'style.css','co_style/co_editor.css','co_style/editor.css','co_js/co_message_format.js','co_js/message_format.js','co_js/co_emoji.js','co_js/emoji.js','co_js/co_editor.js','co_js/editor.js','co_js/co_installation.js','co_js/installation.js','co_js/co_push.js','co_js/push.js','co_js/co_auth_restore.js','co_js/auth_restore.js','co_js/co_app.js','app.js','co_pwa/co_icon.php?size=192','co_pwa/icon.php?size=192'])));
  self.skipWaiting();
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('co-link-') && k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
// Sit ma prednost, aby oprava klienta nezustala schovana za starou cache.
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.origin !== ROOT.origin || ![...STYLE_FILES,...APP_FILES,'style.css','co_style/co_editor.css','co_style/editor.css','co_js/co_message_format.js','co_js/message_format.js','co_js/co_emoji.js','co_js/emoji.js','co_js/co_editor.js','co_js/editor.js','co_js/co_installation.js','co_js/installation.js','co_js/co_push.js','co_js/push.js','co_js/co_auth_restore.js','co_js/auth_restore.js','co_js/co_app.js','app.js','co_pwa/co_icon.php','co_pwa/icon.php'].some(p => url.pathname === ROOT.pathname+p)) return;
  event.respondWith(fetch(event.request).catch(() => caches.match(event.request, {ignoreSearch:true})));
});
self.addEventListener('push', event => {
  let data = {}; try { data = event.data.json(); } catch (_) { /* Prazdny push nezobrazuje soukroma data. */ }
  let url = new URL('./', ROOT);
  try { const candidate = new URL(data.url, ROOT); if(candidate.origin === ROOT.origin && candidate.pathname.startsWith(ROOT.pathname)) url=candidate; } catch (_) {}
  // Stary payload zustava funkcni i pri postupnem nasazeni odesilace a workeru.
  const body = typeof data.body === 'string' && data.body.trim() ? data.body : 'Máte novou zprávu.';
  const options = {body,icon:new URL('co_pwa/co_icon.php?size=192',ROOT).href,
    tag:'co-link-'+String(data.konverzace || ''),data:{url:url.href}};
  if (Number.isFinite(data.timestamp) && data.timestamp > 0) options.timestamp = data.timestamp;
  event.waitUntil(self.registration.showNotification('Co-Link', options));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url=event.notification.data?.url || ROOT.href;
  event.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(async windows => {
    const win=windows.find(w => w.url.startsWith(ROOT.href));
    if(win) { await win.navigate(url); return win.focus(); }
    return self.clients.openWindow(url);
  }));
});
