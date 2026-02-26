// sw.js * Verze: V1 * Aktualizace: 25.2.2026
// Service Worker – test notifikací (bez push serveru)

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Přijímáme zprávu z webu a ukážeme notifikaci.
self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (!data || data.type !== 'SHOW_TEST_NOTIFICATION') {
    return;
  }

  const title = (typeof data.title === 'string' && data.title !== '') ? data.title : 'Comeback';
  const body = (typeof data.body === 'string' && data.body !== '') ? data.body : 'Test notifikace';

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: '/img/logo_comeback.png',
      badge: '/img/logo_comeback.png',
      tag: 'cb-test',
      renotify: false,
      data: { url: '/' }
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const url = (event.notification && event.notification.data && event.notification.data.url)
    ? event.notification.data.url
    : '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const c of clients) {
        if (c.url && c.url.indexOf(url) !== -1) {
          return c.focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});

// sw.js * Verze: V1 * Aktualizace: 25.2.2026 * Počet řádků: 55
// Konec souboru