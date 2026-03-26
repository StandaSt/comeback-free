import { useMutation, useQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import withPage from 'components/withPage';

import editProfileBreadcrumbs from './breadcrumbs';
import EditProfile from './editProfile';
import {
  OnSubmitValues,
  UserEditMyself,
  UserEditMyselfVariables,
  UserGetLogged,
} from './types';

const USER_GET_LOGGED = gql`
  {
    userGetLogged {
      id
      hasOwnCar
      phoneNumber
    }
  }
`;

const USER_EDIT_MYSELF = gql`
  mutation($hasOwnCar: Boolean!, $phoneNumber: String!) {
    userEditMyself(hasOwnCar: $hasOwnCar, phoneNumber: $phoneNumber) {
      id
      hasOwnCar
      phoneNumber
    }
  }
`;

const EditProfileIndex = () => {
  const { data, loading } = useQuery<UserGetLogged>(USER_GET_LOGGED, {
    fetchPolicy: 'no-cache',
  });
  const [edit, { loading: editLoading }] = useMutation<
    UserEditMyself,
    UserEditMyselfVariables
  >(USER_EDIT_MYSELF);
  const router = useRouter();
  const { enqueueSnackbar } = useSnackbar();

  const submitHandler = (values: OnSubmitValues) => {
    edit({ variables: values })
      .then(() => {
        enqueueSnackbar('Profil úspěšně upraven', { variant: 'success' });
        router.push(routes.profile.index);
      })
      .catch(() => {
        enqueueSnackbar('Profil se nepovedlo upravit', { variant: 'error' });
      });
  };

  return (
    <EditProfile
      loading={loading || editLoading}
      phoneNumber={data?.userGetLogged.phoneNumber || ''}
      hasOwnCar={data?.userGetLogged.hasOwnCar || false}
      onSubmit={submitHandler}
    />
  );
};

export default withPage(EditProfileIndex, editProfileBreadcrumbs);
