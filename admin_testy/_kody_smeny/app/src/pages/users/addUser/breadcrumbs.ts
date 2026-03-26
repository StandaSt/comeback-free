import routes from '@shift-planner/shared/config/app/routes';

import usersBreadcrumbs from 'pages/users/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const addUserBreadcrumbs: Breadcrumb[] = [
  ...usersBreadcrumbs,
  { label: 'Přidání uživatele', link: routes.users.addUser },
];

export default addUserBreadcrumbs;
