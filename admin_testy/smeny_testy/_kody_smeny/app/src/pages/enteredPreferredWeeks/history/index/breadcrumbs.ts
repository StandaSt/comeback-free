import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const enteredPreferredWeeksHistoryBreadcrumbs: Breadcrumb[] = [
  { label: 'Zadané požadavky' },
  { label: 'Historie', link: routes.enteredPreferredWeeks.history.index },
];

export default enteredPreferredWeeksHistoryBreadcrumbs;
