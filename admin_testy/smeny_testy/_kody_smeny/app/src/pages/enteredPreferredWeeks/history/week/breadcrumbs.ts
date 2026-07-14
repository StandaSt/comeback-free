import { Breadcrumb } from 'components/withPage/types';

import enteredPreferredWeeksHistoryBreadcrumbs from '../index/breadcrumbs';

const enteredPreferredWeeksHistoryWeekBreadcrumbs: Breadcrumb[] = [
  ...enteredPreferredWeeksHistoryBreadcrumbs,
  { label: 'Týden' },
];

export default enteredPreferredWeeksHistoryWeekBreadcrumbs;
