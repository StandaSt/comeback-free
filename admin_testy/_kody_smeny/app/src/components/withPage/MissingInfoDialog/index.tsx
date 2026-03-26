import { useMutation, useQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import React from 'react';

import OverlayLoading from 'components/OverlayLoading';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';
import MissingInfoDialog from 'components/withPage/MissingInfoDialog/MissingInfoDialog';

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
      workersShiftRoleTypes {
        id
        useCars
      }
    }
  }
`;

const USER_EDIT_MYSELF = gql`
  mutation($hasOwnCar: Boolean, $phoneNumber: String) {
    userEditMyself(hasOwnCar: $hasOwnCar, phoneNumber: $phoneNumber) {
      id
      hasOwnCar
      phoneNumber
    }
  }
`;

const MissingInfoDialogIndex = () => {
  const { data, loading, refetch } = useQuery<UserGetLogged>(USER_GET_LOGGED, {
    fetchPolicy: 'cache-and-network',
  });
  const [editUser, { loading: editLoading }] = useMutation<
    UserEditMyself,
    UserEditMyselfVariables
  >(USER_EDIT_MYSELF);

  const submitHandler = (values: OnSubmitValues) => {
    editUser({
      variables: { hasOwnCar: values.car, phoneNumber: values.phone },
    }).then(() => refetch());
  };

  return (
    <OverlayLoadingContainer>
      <OverlayLoading loading={editLoading} />
      <MissingInfoDialog
        loading={loading}
        car={data?.userGetLogged.hasOwnCar}
        phone={data?.userGetLogged.phoneNumber}
        shiftRoleTypes={data?.userGetLogged.workersShiftRoleTypes || []}
        onSubmit={submitHandler}
      />
    </OverlayLoadingContainer>
  );
};

export default MissingInfoDialogIndex;
