import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const currentEnteredPreferredWeeksBreadcrumbs: Breadcrumb[] = [
  { label: 'Aktuální týden', link: routes.currentEnteredPreferredWeeks },
];

export default currentEnteredPreferredWeeksBreadcrumbs;
