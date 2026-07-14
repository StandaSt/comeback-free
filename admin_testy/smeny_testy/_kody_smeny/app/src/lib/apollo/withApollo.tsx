import { ApolloProvider } from '@apollo/react-hooks';
import { getDataFromTree } from '@apollo/react-ssr';
import ApolloClient, { InMemoryCache } from 'apollo-boost';
import browserCookies from 'browser-cookies';
import cookies from 'next-cookies';
import nextApollo from 'next-with-apollo';
import appConfig from '@shift-planner/shared/config/app';
import React from 'react';

const apolloProvider = ({ Page, props }) => (
  <ApolloProvider client={props.apollo}>
    <Page {...props} />
  </ApolloProvider>
);

export const withApolloPure = nextApollo(
  ({ initialState, ctx }) =>
    new ApolloClient({
      uri: process.browser ? appConfig.api.clientUrl : appConfig.api.serverUrl,
      cache: new InMemoryCache().restore(initialState || {}),
      request: operation => {
        operation.setContext({
          headers: {
            Authorization: `Bearer ${
              // eslint-disable-next-line no-nested-ternary
              process.browser
                ? browserCookies.get(appConfig.cookies.token)
                : ctx
                ? cookies(ctx)[appConfig.cookies.token]
                : ''
            }`,
          },
        });
      },
    }),
  {
    render: apolloProvider,
  },
);

const withApollo = (Component, ssr = false): any => {
  if (ssr) return withApolloPure(Component, { getDataFromTree });

  return withApolloPure(Component);
};

export default withApollo;
