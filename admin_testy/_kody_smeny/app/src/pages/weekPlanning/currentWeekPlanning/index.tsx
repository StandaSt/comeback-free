import React from 'react';
import { fade, useTheme } from '@material-ui/core';
import dayjs from 'dayjs';

import planningResources from 'pages/weekPlanning/shared/resources';
import WeekPlanning from 'pages/weekPlanning/shared/weekPlanning';
import withPage from 'components/withPage';

import currentWeekPlanningBreadcrumbs from './breadcrumbs';

const CurrentWeekPlanning: React.FC = () => {
  const theme = useTheme();

  let defaultDay = dayjs().day() - 1;
  if (defaultDay < 0) {
    defaultDay += 7;
  }

  return (
    <WeekPlanning
      title="Aktuální týden"
      skipWeeks={-1}
      backgroundColor={fade(theme.palette.primary.main, 0.05)}
      defaultDay={defaultDay}
    />
  );
};

export default withPage(
  CurrentWeekPlanning,
  currentWeekPlanningBreadcrumbs,
  planningResources,
);
