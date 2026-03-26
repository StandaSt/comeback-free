import rolesBreadcrumbs from 'pages/roles/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const resourceDetailBreadcrumbs: Breadcrumb[] = [
  ...rolesBreadcrumbs,
  { label: 'Detail pravomoce' },
];

export default resourceDetailBreadcrumbs;
