import React from 'react';
import { fade, useTheme } from '@material-ui/core';

import workingWeekResources from 'pages/workingWeek/shared/resources';
import withPage from 'components/withPage';
import WorkingWeek from 'components/WorkingWeek';

import currentWorkingWeekBreadcrumbs from './breadcrumbs';

const CurrentWorkingWeek: React.FC = () => {
  const theme = useTheme();

  return (
    <WorkingWeek
      skipWeeks={-1}
      title="Aktuální týdenní rozvrh"
      backgroundColor={fade(theme.palette.primary.main, 0.05)}
    />
  );
};

export default withPage(
  CurrentWorkingWeek,
  currentWorkingWeekBreadcrumbs,
  workingWeekResources,
);
