import shiftWeekTemplatesBreadcrumbs from 'pages/shiftWeekTemplates/index/breadcrumbs';
import { Breadcrumb } from 'components/withPage/types';

const weekBreadcrumbs: Breadcrumb[] = [
  ...shiftWeekTemplatesBreadcrumbs,
  { label: 'Šablona' },
];

export default weekBreadcrumbs;
