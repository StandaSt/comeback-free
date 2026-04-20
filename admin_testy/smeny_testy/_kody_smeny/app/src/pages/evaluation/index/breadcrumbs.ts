import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const evaluationBreadcrumbs: Breadcrumb[] = [
  { label: 'Hodnocení', link: routes.evaluation.index },
];

export default evaluationBreadcrumbs;
