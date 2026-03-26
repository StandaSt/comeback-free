import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const nextWeekSummaryBreadcrumbs: Breadcrumb[] = [
  { label: 'Aktuální týden', link: routes.currentWeekSummary },
];

export default nextWeekSummaryBreadcrumbs;
