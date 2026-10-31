/* Co-Link: staticke prostredi, obnova prihlaseni a push s odesilatelem a uryvkem; soukrome odpovedi API se necachuji. */
const VERSION = 'co-link-v6';
const ROOT = new URL('./', self.location.href);
self.addEventListener('install', event => {
  event.waitUntil(caches.open(VERSION).then(cache => cache.addAll(['style.css','editor.css','message_format.js','emoji.js','editor.js','installation.js','push.js','auth_restore.js','app.js','icon.php?size=192'])));
  self.skipWaiting();
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('co-link-') && k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
// Sit ma prednost, aby oprava klienta nezustala schovana za starou cache.
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.origin !== ROOT.origin || !['style.css','editor.css','message_format.js','emoji.js','editor.js','installation.js','push.js','auth_restore.js','app.js','icon.php'].some(p => url.pathname === ROOT.pathname+p)) return;
  event.respondWith(fetch(event.request).catch(() => caches.match(event.request, {ignoreSearch:true})));
});
self.addEventListener('push', event => {
  let data = {}; try { data = event.data.json(); } catch (_) { /* Prazdny push nezobrazuje soukroma data. */ }
  let url = new URL('./', ROOT);
  try { const candidate = new URL(data.url, ROOT); if(candidate.origin === ROOT.origin && candidate.pathname.startsWith(ROOT.pathname)) url=candidate; } catch (_) {}
  // Stary payload zustava funkcni i pri postupnem nasazeni odesilace a workeru.
  const body = typeof data.body === 'string' && data.body.trim() ? data.body : 'Máte novou zprávu.';
  const options = {body,icon:new URL('icon.php?size=192',ROOT).href,
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
