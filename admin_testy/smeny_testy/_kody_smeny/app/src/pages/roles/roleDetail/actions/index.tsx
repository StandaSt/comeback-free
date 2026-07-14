import { useMutation } from '@apollo/react-hooks';
import { Typography } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar, WithSnackbarProps } from 'notistack';
import apiErrors from '@shift-planner/shared/config/api/errors';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';

const ROLE_REMOVE = gql`
  mutation($id: Int!) {
    roleRemove(id: $id)
  }
`;

const ActionsIndex: React.FC<WithSnackbarProps> = props => {
  const router = useRouter();
  const [roleRemove, { loading: mutationLoading }] = useMutation(ROLE_REMOVE);

  const submitHandler = (): void => {
    roleRemove({
      variables: { id: +router.query.roleId },
    })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Role úspěšně odstraněna', {
            variant: 'success',
          });
          router.push(routes.roles.index);
        }
      })
      .catch(err => {
        if (
          err.graphQLErrors.some(
            e => e.message?.message === apiErrors.remove.roleMinimalCount,
          )
        )
          props.enqueueSnackbar('V systému musí být minimálně jedna role', {
            variant: 'error',
          });
        else if (
          err.graphQLErrors.some(
            e => e.message?.message === apiErrors.remove.resourceConditions,
          )
        ) {
          props.enqueueSnackbar(
            'Role nejde odstranit, protože by nebyli splněny podmínky zdrojů',
            {
              variant: 'error',
            },
          );
        } else props.enqueueSnackbar('Něco se pokazilo', { variant: 'error' });
      });
  };

  return (
    <>
      <Typography variant="h6">Odstranit</Typography>
      <Actions
        actions={[
          {
            id: 1,
            element: (
              <LoadingButton
                loading={mutationLoading}
                key="actionRemove"
                color="secondary"
                variant="contained"
                onClick={submitHandler}
              >
                Odstranit
              </LoadingButton>
            ),
          },
        ]}
      />
    </>
  );
};

export default withSnackbar(ActionsIndex);
