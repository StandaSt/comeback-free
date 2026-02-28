// sw.js * Verze: V2 * Aktualizace: 26.2.2026
// Service Worker – Web Push + test notifikací

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// TEST: Přijímáme zprávu z webu a ukážeme notifikaci.
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

// REAL: Web Push event
self.addEventListener('push', (event) => {
  let data = {};
  try {
    if (event.data) {
      data = event.data.json();
    }
  } catch (e) {
    data = {};
  }

  let title = 'Comeback';
  let body = 'Notifikace';
  let url = '/';

  if (data && typeof data === 'object') {
    if (typeof data.title === 'string' && data.title !== '') {
      title = data.title;
    }
    if (typeof data.body === 'string' && data.body !== '') {
      body = data.body;
    }
    if (typeof data.url === 'string' && data.url !== '') {
      url = data.url;
    }
  }

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: '/img/logo_comeback.png',
      badge: '/img/logo_comeback.png',
      tag: 'cb-push',
      renotify: true,
      data: { url: url }
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  let url = '/';
  try {
    if (event.notification && event.notification.data && event.notification.data.url) {
      url = event.notification.data.url;
    }
  } catch (e) {
    url = '/';
  }

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

// sw.js * Verze: V2 * Aktualizace: 26.2.2026 * Počet řádků: 100
// Konec souboru