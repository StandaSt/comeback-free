import React from 'react';

import enteredPreferredWeeksResources from 'pages/enteredPreferredWeeks/resources';
import EnteredPreferredWeeks from 'components/EnteredPreferredWeeks';
import withPage from 'components/withPage';

import currentEnteredPreferredWeeksBreadcrumbs from './breadcrumbs';

const CurrentEnteredPreferredWeek: React.FC = () => (
  <EnteredPreferredWeeks
    skipWeeks={-1}
    title="Zadané požadavky - aktuální týden"
  />
);

export default withPage(
  CurrentEnteredPreferredWeek,
  currentEnteredPreferredWeeksBreadcrumbs,
  enteredPreferredWeeksResources,
);
