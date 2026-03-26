import { useMutation } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import withPage from 'components/withPage';

import AddUser from './addUser';
import addUserBreadcrumbs from './breadcrumbs';
import { AddUserIndexProps, UserRegister, UserRegisterVars } from './types';
import UserLoginDataDialog from './userLoginDataDialog';

const USER_REGISTER = gql`
  mutation($email: String!, $name: String!, $surname: String!) {
    userRegister(email: $email, name: $name, surname: $surname) {
      id
      email
      generatedPassword
    }
  }
`;

const AddUserIndex: React.FC<AddUserIndexProps> = props => {
  const [userRegister, { loading, data }] = useMutation<
    UserRegister,
    UserRegisterVars
  >(USER_REGISTER);
  const router = useRouter();

  const onSubmit = (email: string, name: string, surname: string): void => {
    userRegister({ variables: { email, name, surname } })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Uživatel úspěšně přidán', {
            variant: 'success',
          });
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Uživatele se nepovedlo přidat', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <AddUser onSubmit={onSubmit} loading={loading} />
      <UserLoginDataDialog
        open={Boolean(data)}
        close={() => router.push(routes.users.index)}
        email={data?.userRegister.email}
        password={data?.userRegister.generatedPassword}
      />
    </>
  );
};

export default withPage(withSnackbar(AddUserIndex), addUserBreadcrumbs, [
  resources.users.add,
]);
