import rolesBreadcrumbs from 'pages/roles/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const roleDetailBreadcrumbs: Breadcrumb[] = [
  ...rolesBreadcrumbs,
  { label: 'Detail role' },
];

export default roleDetailBreadcrumbs;
