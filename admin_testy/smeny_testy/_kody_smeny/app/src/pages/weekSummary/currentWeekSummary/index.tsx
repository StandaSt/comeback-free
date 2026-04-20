import React from 'react';

import ShiftWeekSummaryIndex from 'components/ShiftWeekSummary';
import withPage from 'components/withPage';

import currentWeekSummaryBreadcrumbs from './breadcrumbs';
import currentWeekSummaryResources from './resources';

const CurrentWeekSummary: React.FC = () => (
  <ShiftWeekSummaryIndex skipWeeks={-1} title="Aktuální týden" />
);

export default withPage(
  CurrentWeekSummary,
  currentWeekSummaryBreadcrumbs,
  currentWeekSummaryResources,
);
