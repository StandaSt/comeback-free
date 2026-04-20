import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import withPage from 'components/withPage';

import Dashboard from './dashboard';

const DashboardIndex = () => <Dashboard />;

export default withPage(DashboardIndex, [
  { label: 'Přehled', link: routes.dashboard },
]);
