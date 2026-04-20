import React from 'react';
import { fade, useTheme } from '@material-ui/core';

import WeekPlanning from 'pages/weekPlanning/shared/weekPlanning';
import withPage from 'components/withPage';

import planningResources from '../shared/resources';

import nextWeekPlanningBreadcrumbs from './breadcrumbs';

const NextWeekPlanning: React.FC = () => {
  const theme = useTheme();

  return (
    <WeekPlanning
      title="Následující týden"
      skipWeeks={0}
      backgroundColor={fade(theme.palette.secondary.main, 0.05)}
    />
  );
};

export default withPage(
  NextWeekPlanning,
  nextWeekPlanningBreadcrumbs,
  planningResources,
);
