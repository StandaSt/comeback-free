import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const nextEnteredPreferredWeeksBreadcrumbs: Breadcrumb[] = [
  { label: 'Následující týden', link: routes.currentEnteredPreferredWeeks },
];

export default nextEnteredPreferredWeeksBreadcrumbs;
