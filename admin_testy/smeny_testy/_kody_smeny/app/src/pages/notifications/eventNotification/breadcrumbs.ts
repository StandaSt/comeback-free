import { Breadcrumb } from 'components/withPage/types';

import notificationsBreadcrumbs from '../index/breadcrumbs';

const eventNotificationBreadcrumbs: Breadcrumb[] = [
  ...notificationsBreadcrumbs,
  { label: 'Událostní notifikace' },
];

export default eventNotificationBreadcrumbs;
