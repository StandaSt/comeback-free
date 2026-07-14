import routes from '@shift-planner/shared/config/app/routes';

import profileBreadcrumbs from 'pages/profile/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const editProfileBreadcrumbs: Breadcrumb[] = [
  ...profileBreadcrumbs,
  { label: 'Upravení profilu', link: routes.profile.changePassword },
];

export default editProfileBreadcrumbs;
