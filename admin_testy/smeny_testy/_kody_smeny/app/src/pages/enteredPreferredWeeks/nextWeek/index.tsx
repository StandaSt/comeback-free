import React from 'react';

import enteredPreferredWeeksResources from 'pages/enteredPreferredWeeks/resources';
import EnteredPreferredWeeks from 'components/EnteredPreferredWeeks';
import withPage from 'components/withPage';

import nextEnteredPreferredWeeksBreadcrumbs from './breadcrumbs';

const NextEnteredPreferredWeek: React.FC = () => (
  <EnteredPreferredWeeks
    skipWeeks={0}
    title="Zadané požadavky - následující týden"
  />
);

export default withPage(
  NextEnteredPreferredWeek,
  nextEnteredPreferredWeeksBreadcrumbs,
  enteredPreferredWeeksResources,
);
