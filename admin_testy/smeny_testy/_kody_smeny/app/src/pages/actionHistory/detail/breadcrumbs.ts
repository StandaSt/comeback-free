import actionHistoryBreadcrumbs from 'pages/actionHistory/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const actionHistoryDetailBreadcrumbs: Breadcrumb[] = [
  ...actionHistoryBreadcrumbs,
  { label: 'Detail' },
];
export default actionHistoryDetailBreadcrumbs;
