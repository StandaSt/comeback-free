import shiftRoleTypesBreadcrumbs from 'pages/shiftRoleTypes/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const editBreadcrumbs: Breadcrumb[] = [
  ...shiftRoleTypesBreadcrumbs,
  { label: 'Editace typu slotů' },
];

export default editBreadcrumbs;
