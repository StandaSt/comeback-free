import usersBreadcrumbs from 'pages/users/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const userDetailBreadcrumbs: Breadcrumb[] = [
  ...usersBreadcrumbs,
  { label: 'Detail uživatele' },
];

export default userDetailBreadcrumbs;
