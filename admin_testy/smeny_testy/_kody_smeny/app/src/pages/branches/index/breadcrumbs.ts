import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const branchesBreadcrumbs: Breadcrumb[] = [
  { label: 'Pobočky', link: routes.branches.index },
];

export default branchesBreadcrumbs;
