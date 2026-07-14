import { Breadcrumb } from 'components/withPage/types';

import evaluationBreadcrumbs from '../index/breadcrumbs';

const detailBreadcrumbs: Breadcrumb[] = [
  ...evaluationBreadcrumbs,
  { label: 'Uživatel' },
];

export default detailBreadcrumbs;
