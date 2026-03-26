import routes from '@shift-planner/shared/config/app/routes';

import shiftRoleTypesBreadcrumbs from 'pages/shiftRoleTypes/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const addBreadcrumbs: Breadcrumb[] = [
  ...shiftRoleTypesBreadcrumbs,
  { label: 'Přidání typu slotů', link: routes.shiftRoleTypes.add },
];

export default addBreadcrumbs;
