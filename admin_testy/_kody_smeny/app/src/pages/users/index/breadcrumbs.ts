import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const usersBreadcrumbs: Breadcrumb[] = [
  { label: 'Uživatelé', link: routes.users.index },
];

export default usersBreadcrumbs;
