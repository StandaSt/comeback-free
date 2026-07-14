import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const actionHistoryBreadcrumbs: Breadcrumb[] = [
  { label: 'Logování', link: routes.actionHistory.index },
];

export default actionHistoryBreadcrumbs;
