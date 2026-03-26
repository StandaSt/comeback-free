import routes from '@shift-planner/shared/config/app/routes';

import profileBreadcrumbs from 'pages/profile/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const changePasswordBreadcrumbs: Breadcrumb[] = [
  ...profileBreadcrumbs,
  { label: 'Změna hesla', link: routes.profile.changePassword },
];

export default changePasswordBreadcrumbs;
