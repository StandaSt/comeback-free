/* eslint-disable no-restricted-globals */
let subscription;
let apiURL;
let publicKey;

const urlB64ToUint8Array = base64String => {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }

  return outputArray;
};

const saveSubscription = async (subscription, cookie) => {
  const response = await fetch(apiURL, {
    method: 'post',
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      Authorization: `Bearer ${cookie}`,
    },
    body: JSON.stringify({
      operationName: null,
      query:
        'mutation PushNotificationServiceSaveSubscription ($subscription: String!){notificationSaveSubscription(subscription: $subscription)}',
      variables: {
        subscription: JSON.stringify(subscription),
      },
    }),
  });

  return response.json();
};

const showLocalNotification = (title, body, redirect, swRegistration) => {
  const options = {
    body,
    icon: '/static/favicon.ico',
    click_action: 'http://yourlink.cz',
    data: {
      redirect,
    },
    vibrate: [100, 100, 100, 100, 100],
  };
  swRegistration.showNotification(title, options);
};

const activate = async () => {
  console.log('activate push notifications service');
  apiURL = new URL(location).searchParams.get('apiURL');
  publicKey = new URL(location).searchParams.get('publicKey');
  try {
    const applicationServerKey = urlB64ToUint8Array(publicKey);
    const options = { applicationServerKey, userVisibleOnly: true };

    subscription = await self.registration.pushManager.subscribe(options);
    // eslint-disable-next-line no-console
    console.log('Push manger subscribed', subscription);
  } catch (err) {
    // eslint-disable-next-line no-console
    console.log('Error', err);
  }
};

self.addEventListener('install', event => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', async event => {
  event.waitUntil(self.clients.claim());
  await activate();
});

self.addEventListener('message', async event => {
  if (event.data.type === 'COOKIE_CHANGE') {
    // eslint-disable-next-line no-console
    console.log(
      'subscription saved',
      await saveSubscription(subscription, event.data.cookie),
    );
  } else if (event.data.type === 'API_URL_CHANGE') {
    apiURL = event.data.apiURL;
  } else if (event.data.type === 'REACTIVATE') {
    await activate();
  } else if (event.data.type === 'CHECK_SUBSCRIPTION') {
    const sub = await self.registration.pushManager.getSubscription();
    // eslint-disable-next-line no-console
    console.log('updating subscription', sub);
    if (sub === null) {
      activate();
    } else {
      subscription = sub;
    }
  }
});

self.addEventListener('push', event => {
  const data = JSON.parse(event.data.text());

  showLocalNotification(
    'Směny',
    data.description,
    data.redirect,
    self.registration,
  );
});

self.addEventListener('notificationclick', event => {
  // eslint-disable-next-line no-undef
  clients.openWindow(event.notification.data.redirect);
});
