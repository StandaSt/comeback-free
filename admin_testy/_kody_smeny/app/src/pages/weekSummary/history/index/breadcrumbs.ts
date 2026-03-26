import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const weekSummaryHistoryBreadcrumbs: Breadcrumb[] = [
  { label: 'Naplánované směny' },
  { label: 'Historie', link: routes.weekSummary.history.index },
];

export default weekSummaryHistoryBreadcrumbs;
