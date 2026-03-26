import rolesBreadcrumbs from 'pages/roles/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const resourceCategoryDetailBreadcrumbs: Breadcrumb[] = [
  ...rolesBreadcrumbs,
  { label: 'Detail kategorie' },
];

export default resourceCategoryDetailBreadcrumbs;
