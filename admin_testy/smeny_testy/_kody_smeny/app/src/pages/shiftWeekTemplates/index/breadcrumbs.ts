import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const shiftWeekTemplatesBreadcrumbs: Breadcrumb[] = [
  { label: 'Šablony', link: routes.shiftWeekTemplates.index },
];

export default shiftWeekTemplatesBreadcrumbs;
