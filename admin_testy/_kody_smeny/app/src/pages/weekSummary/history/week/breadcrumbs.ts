import { Breadcrumb } from 'components/withPage/types';

import weekSummaryHistoryBreadcrumbs from '../index/breadcrumbs';

const weekSummaryHistoryWeekBreadcrumbs: Breadcrumb[] = [
  ...weekSummaryHistoryBreadcrumbs,
  { label: 'Týden' },
];

export default weekSummaryHistoryWeekBreadcrumbs;
