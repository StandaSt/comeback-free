import React from 'react';
import { useRouter } from 'next/router';

import ShiftWeekSummaryIndex from 'components/ShiftWeekSummary';
import withPage from 'components/withPage';

import weekSummaryHistoryResources from '../index/resources';

import weekSummaryHistoryWeekBreadcrumbs from './breadcrumbs';

const WeekSummaryHistoryWeek: React.FC = () => {
  const router = useRouter();

  return (
    <ShiftWeekSummaryIndex
      skipWeeks={+router.query.skipWeeks}
      title="Naplánované směny"
    />
  );
};

export default withPage(
  WeekSummaryHistoryWeek,
  weekSummaryHistoryWeekBreadcrumbs,
  weekSummaryHistoryResources,
);
