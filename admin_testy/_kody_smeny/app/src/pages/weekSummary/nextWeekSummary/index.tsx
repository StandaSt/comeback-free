import React from 'react';

import ShiftWeekSummaryIndex from 'components/ShiftWeekSummary';
import withPage from 'components/withPage';

import nextWeekSummaryBreadcrumbs from './breadcrumbs';
import nextWeekSummaryResources from './resources';

const NextWeekSummary: React.FC = () => (
  <ShiftWeekSummaryIndex skipWeeks={0} title="Následující týden" />
);

export default withPage(
  NextWeekSummary,
  nextWeekSummaryBreadcrumbs,
  nextWeekSummaryResources,
);
