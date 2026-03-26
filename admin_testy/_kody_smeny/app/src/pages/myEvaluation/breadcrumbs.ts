import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const myEvaluationBreadcrumbs: Breadcrumb[] = [
  { label: 'Moje hodnocení', link: routes.myEvaluation.index },
];

export default myEvaluationBreadcrumbs;
