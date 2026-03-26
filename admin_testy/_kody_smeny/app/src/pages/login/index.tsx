import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { useCookies } from 'react-cookie';
import appConfig from '@shift-planner/shared/config/app';
import React, { useState } from 'react';

import withApollo from 'lib/apollo/withApollo';

import { updatePushNotificationsService } from '../../components/registerServiceWorkers';

import Login from './login';
import { UserLogin } from './types';

const USER_LOGIN = gql`
  query userLogin($email: String!, $password: String!) {
    userLogin(email: $email, password: $password) {
      id
      accessToken
      name
      surname
      email
      darkTheme
      unconfirmedNextPreferredWeek
      roles {
        id
        resources {
          id
          name
        }
      }
    }
  }
`;

const LoginIndex = () => {
  const [userLogin, { loading, data, error }] = useLazyQuery<UserLogin>(
    USER_LOGIN,
    {
      fetchPolicy: 'no-cache',
    },
  );
  const [, setCookie, removeCookie] = useCookies();
  const [state, setState] = useState({ loggedIn: false });
  const router = useRouter();

  if (data && !loading && !state.loggedIn) {
    setState({ ...state, loggedIn: true });

    removeCookie(appConfig.cookies.token);
    removeCookie(appConfig.cookies.darkTheme);
    setCookie(appConfig.cookies.token, data.userLogin.accessToken);
    setCookie(appConfig.cookies.darkTheme, data.userLogin.darkTheme);
    updatePushNotificationsService();
    if (data?.userLogin.unconfirmedNextPreferredWeek) {
      router.push(appConfig.routes.nextWorkingWeek);
    } else if (router.query.redirectURL) {
      try {
        router.push({
          pathname: router.query.redirectURL.toString(),
          // eslint-disable-next-line @typescript-eslint/ban-ts-comment
          // @ts-ignore
          query: JSON.parse(router.query.redirectQuery) || {},
        });
      } catch {
        router.push(appConfig.routes.dashboard);
      }
    } else {
      router.push(appConfig.routes.dashboard);
    }
  }

  const submitHandler = (email, password) => {
    userLogin({ variables: { email, password } });
  };

  return (
    <Login
      onSubmit={submitHandler}
      badInputs={error !== undefined}
      loading={loading}
    />
  );
};

export default withApollo(LoginIndex);
