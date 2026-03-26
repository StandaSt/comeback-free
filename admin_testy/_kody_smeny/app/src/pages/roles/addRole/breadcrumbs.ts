import routes from '@shift-planner/shared/config/app/routes';

import rolesBreadcrumbs from 'pages/roles/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const addRoleBreadcrumbs: Breadcrumb[] = [
  ...rolesBreadcrumbs,
  { label: 'Přidání role', link: routes.roles.addRole },
];

export default addRoleBreadcrumbs;
