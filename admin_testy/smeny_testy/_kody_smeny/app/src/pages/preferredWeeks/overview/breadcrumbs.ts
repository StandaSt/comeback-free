import preferredWeeksBreadcrumbs from 'pages/preferredWeeks/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const overviewBreadcrumbs: Breadcrumb[] = [
  ...preferredWeeksBreadcrumbs,
  { label: 'Přehled' },
];

export default overviewBreadcrumbs;
