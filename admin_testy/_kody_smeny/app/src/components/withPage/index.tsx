import { useQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import dynamic from 'next/dynamic';
import { useCookies } from 'react-cookie';
import appConfig from '@shift-planner/shared/config/app';
import React from 'react';

import withApollo from 'lib/apollo/withApollo';
import useResources from 'components/resources/useResources';

import Loading from './Loading';
import Page from './Page';
import { Breadcrumb, UserGetLogged } from './types';

const NoAccess = dynamic(import('components/withPage/NoAccess'), {
  ssr: false,
});

const USER_GET_LOGGED = gql`
  {
    userGetLogged {
      id
      name
      surname
      darkTheme
      totalEvaluationScore
    }
  }
`;

const withPage = (
  Component: React.ComponentType,
  breadcrumbs: Breadcrumb[],
  requiredResources: string[] = [],
  apolloSsr = false,
): React.FC => {
  const WithPage = withApollo((props: any) => {
    const hasAccess = useResources(requiredResources);
    const [cookies, setCookie] = useCookies();

    const { data, error } = useQuery<UserGetLogged>(USER_GET_LOGGED, {
      fetchPolicy: 'cache-and-network',
      pollInterval: 60000,
    });

    if (data) {
      if (
        cookies[appConfig.cookies.darkTheme] !==
        data.userGetLogged.darkTheme.toString()
      ) {
        setCookie(appConfig.cookies.darkTheme, data.userGetLogged.darkTheme);
      }
    }

    const showPage = !error && hasAccess && data;
    const showNoAccess = error || !hasAccess;
    const showNothing = !data && !error;

    return (
      <>
        {showNothing && <Loading />}
        {showNoAccess && <NoAccess />}
        {(showPage || !process.browser) && (
          <Page
            user={data?.userGetLogged}
            Component={Component}
            breadcrumbs={breadcrumbs}
            {...props}
          />
        )}
      </>
    );
  }, apolloSsr);

  return WithPage;
};

export default withPage;
