import React from 'react';

import Paper from 'components/Paper';
import withPage from 'components/withPage';

import newsBreadcrumbs from './breadcrumbs';
import News from './news';
import newsResources from './resources';

const NewsIndex: React.FC = () => (
  <Paper title="Novinky">
    <News />
  </Paper>
);

export default withPage(NewsIndex, newsBreadcrumbs, newsResources);
