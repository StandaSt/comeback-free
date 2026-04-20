import React from 'react';
import { useRouter } from 'next/router';

import withPage from 'components/withPage';
import EnteredPreferredWeeks from 'components/EnteredPreferredWeeks';

import enteredPreferredWeeksResources from '../../resources';

import enteredPreferredWeeksHistoryWeekBreadcrumbs from './breadcrumbs';

const EnteredPreferredWeeksHistoryWeek: React.FC = () => {
  const router = useRouter();

  return (
    <EnteredPreferredWeeks
      skipWeeks={+router.query.skipWeeks}
      title="Zadané požadavky"
    />
  );
};

export default withPage(
  EnteredPreferredWeeksHistoryWeek,
  enteredPreferredWeeksHistoryWeekBreadcrumbs,
  enteredPreferredWeeksResources,
);
