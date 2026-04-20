import cookies from 'browser-cookies';
import appConfig from '@shift-planner/shared/config/app';

export const checkPushNotifications = (): boolean => {
  return (
    process.browser && 'serviceWorker' in navigator && 'PushManager' in window
  );
};

export const checkNotificationPermission = (): boolean => {
  return window.Notification.permission === 'granted';
};

const requestNotificationPermission = async (): Promise<boolean> => {
  const permission = await window.Notification.requestPermission();

  return permission === 'granted';
};

const registerServiceWorkers = async (): Promise<void> => {
  const serviceWorker = await navigator.serviceWorker.register(
    `/unifiedService.js?apiURL=${appConfig.api.clientUrl}&publicKey=${appConfig.notifications.public}&version=${appConfig.notifications.version}`,
  );

  const permission = await requestNotificationPermission();
  if (!permission) {
    // eslint-disable-next-line no-console
    console.error('You did not granted permission for push notifications');
  }
};

if (process.browser && 'permissions' in navigator) {
  navigator.permissions
    .query({ name: 'notifications' })
    .then(notificationPermission => {
      notificationPermission.onchange = function () {
        navigator.serviceWorker.controller?.postMessage({
          type: 'REACTIVATE',
        });
      };
    });
}

export const checkSubscription = (): void => {
  if (checkPushNotifications()) {
    navigator.serviceWorker?.controller?.postMessage({
      type: 'CHECK_SUBSCRIPTION',
    });
  }
};

export const updatePushNotificationsService = (): void => {
  if (checkPushNotifications()) {
    navigator.serviceWorker?.controller?.postMessage({
      type: 'API_URL_CHANGE',
      apiURL: appConfig.api.clientUrl,
    });
    navigator.serviceWorker?.controller?.postMessage({
      type: 'COOKIE_CHANGE',
      cookie: cookies.get(appConfig.cookies.token),
    });
  }
};
export default registerServiceWorkers;
