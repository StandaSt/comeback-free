import branchesBreadcrumbs from 'pages/branches/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const AddBreadcrumbs: Breadcrumb[] = [
  ...branchesBreadcrumbs,
  { label: 'Přidání pobočky' },
];

export default AddBreadcrumbs;
