import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const currentWorkingWeekBreadcrumbs: Breadcrumb[] = [
  { label: 'Aktuální týdenní rozvrh', link: routes.currentWorkingWeek },
];

export default currentWorkingWeekBreadcrumbs;
