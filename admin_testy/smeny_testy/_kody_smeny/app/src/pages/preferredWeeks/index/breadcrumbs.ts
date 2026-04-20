import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const preferredWeeksBreadcrumbs: Breadcrumb[] = [
  { label: 'Požadavky', link: routes.preferredWeeks.index },
];
export default preferredWeeksBreadcrumbs;
