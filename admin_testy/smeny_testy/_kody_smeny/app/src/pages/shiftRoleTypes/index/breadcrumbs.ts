import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const shiftRoleTypesBreadcrumbs: Breadcrumb[] = [
  { label: 'Typy slotů', link: routes.shiftRoleTypes.index },
];

export default shiftRoleTypesBreadcrumbs;
