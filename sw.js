// Service Worker zobrazuje Web Push a otevírá pouze URL určenou konkrétním typem oznámení.

const CB_SW_ROOT = new URL('./', self.registration.scope);
const CB_LOGO_URL = new URL('common/img/logo_comeback.png', CB_SW_ROOT).toString();

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
      icon: CB_LOGO_URL,
      badge: CB_LOGO_URL,
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
  let type = '';

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
    if (typeof data.type === 'string') {
      type = data.type;
    }
  }

  // Technická chyba na zamčené obrazovce neprozrazuje uživatele ani detail; ten zobrazí tokenizovaný modál.
  if (type === 'SYSTEM_ERROR_ADMIN' || type === 'HELPDESK_CREATE_ERROR_ADMIN') {
    if (type === 'SYSTEM_ERROR_ADMIN') {
      title = 'Chyba v IS';
    }
    body = '';
  }

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: CB_LOGO_URL,
      badge: CB_LOGO_URL,
      tag: 'cb-push',
      renotify: true,
      data: { url: url, type: type }
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

// Konec souboru
