import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const notificationsBreadcrumbs: Breadcrumb[] = [
  { label: 'Notifikace', link: routes.notifications.index },
];

export default notificationsBreadcrumbs;
