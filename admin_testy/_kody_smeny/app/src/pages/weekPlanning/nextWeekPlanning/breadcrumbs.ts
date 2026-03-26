import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const nextWeekPlanningBreadcrumbs: Breadcrumb[] = [
  { label: 'Následující týden', link: routes.nextWeekPlanning.index },
];

export default nextWeekPlanningBreadcrumbs;
