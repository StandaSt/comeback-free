import { useMutation } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import React, { useState } from 'react';

import withApollo from 'lib/apollo/withApollo';
import SuccessModal from 'pages/registration/successModal';

import apiErrors from '../../../../shared/config/api/errors';

import Registration from './registration';
import { FormTypes, UserRegisterMyself, UserRegisterMyselfVars } from './types';

const USER_REGISTER_MYSELF = gql`
  mutation(
    $email: String!
    $name: String!
    $surname: String!
    $password: String!
  ) {
    userRegisterMyself(
      email: $email
      name: $name
      surname: $surname
      password: $password
    )
  }
`;

const RegistrationIndex = () => {
  const { enqueueSnackbar } = useSnackbar();
  const [userRegisterMyself, { loading }] = useMutation<
    UserRegisterMyself,
    UserRegisterMyselfVars
  >(USER_REGISTER_MYSELF);
  const [modal, setModal] = useState(false);

  const submitHandler = (values: FormTypes) => {
    userRegisterMyself({
      variables: {
        email: values.email,
        name: values.name,
        surname: values.surname,
        password: values.password1,
      },
    })
      .then(() => {
        setModal(true);
      })
      .catch(error => {
        if (
          error.graphQLErrors.some(
            e => e.message.message === apiErrors.db.duplicate,
          )
        ) {
          enqueueSnackbar('Uživatel s tímto emailem již existuje', {
            variant: 'warning',
          });
        } else {
          enqueueSnackbar('Registrace se nepovedla', {
            variant: 'error',
          });
        }
      });
  };

  return (
    <>
      <Registration onSubmit={submitHandler} loading={loading} />
      <SuccessModal open={modal} />
    </>
  );
};

export default withApollo(RegistrationIndex);
