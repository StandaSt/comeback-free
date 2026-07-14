import { Breadcrumb } from 'components/withPage/types';

import notificationsBreadcrumbs from '../../index/breadcrumbs';

const timeNotificationBreadcrumbs: Breadcrumb[] = [
  ...notificationsBreadcrumbs,
  { label: 'Časové notifikace' },
];

export default timeNotificationBreadcrumbs;
