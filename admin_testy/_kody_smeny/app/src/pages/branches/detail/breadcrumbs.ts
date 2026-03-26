import branchesBreadcrumbs from 'pages/branches/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const detailBreadcrumbs: Breadcrumb[] = [
  ...branchesBreadcrumbs,
  { label: 'Detail' },
];

export default detailBreadcrumbs;
