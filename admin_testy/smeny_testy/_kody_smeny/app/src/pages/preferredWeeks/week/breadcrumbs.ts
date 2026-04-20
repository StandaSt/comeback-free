import preferredWeeksBreadcrumbs from 'pages/preferredWeeks/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const weekBreadcrumbs: Breadcrumb[] = [
  ...preferredWeeksBreadcrumbs,
  { label: 'Týden' },
];

export default weekBreadcrumbs;
