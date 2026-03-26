import React from 'react';
import { fade, useTheme } from '@material-ui/core';

import workingWeekResources from 'pages/workingWeek/shared/resources';
import withPage from 'components/withPage';
import WorkingWeek from 'components/WorkingWeek';

import currentWorkingWeekBreadcrumbs from './breadcrumbs';

const NextWorkingWeek: React.FC = () => {
  const theme = useTheme();

  return (
    <WorkingWeek
      skipWeeks={0}
      title="Následující týdenní rozvrh"
      backgroundColor={fade(theme.palette.secondary.main, 0.05)}
    />
  );
};

export default withPage(
  NextWorkingWeek,
  currentWorkingWeekBreadcrumbs,
  workingWeekResources,
);
