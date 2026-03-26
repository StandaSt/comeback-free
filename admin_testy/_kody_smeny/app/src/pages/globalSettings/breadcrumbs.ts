import routes from '@shift-planner/shared/config/app/routes';

import { Breadcrumb } from 'components/withPage/types';

const globalSettingsBreadcrumbs: Breadcrumb[] = [
  { label: 'Globální nastavení', link: routes.globalSettings },
];

export default globalSettingsBreadcrumbs;
