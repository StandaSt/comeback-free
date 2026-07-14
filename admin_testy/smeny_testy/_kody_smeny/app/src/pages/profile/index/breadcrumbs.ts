import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const profileBreadcrumbs: Breadcrumb[] = [
  { label: 'Profil', link: routes.profile.index },
];

export default profileBreadcrumbs;
