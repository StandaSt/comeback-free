import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const currentWeekPlanningBreadcrumbs: Breadcrumb[] = [
  { label: 'Aktuální týden', link: routes.currentWeekPlanning },
];

export default currentWeekPlanningBreadcrumbs;
