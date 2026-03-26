import React, { useState } from 'react';
import {
  Box,
  Dialog,
  DialogActions,
  DialogTitle,
  Typography,
} from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useMutation } from '@apollo/react-hooks';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';
import routes from '@shift-planner/shared/config/app/routes';

import LoadingButton from 'components/LoadingButton';

import useResources from '../../../../components/resources/useResources';
import resources from '../../../../../../shared/config/api/resources';

import { UserDeleteProps, UserRemove, UserRemoveVariables } from './types';

const USER_REMOVE = gql`
  mutation($id: Int!) {
    userRemove(id: $id)
  }
`;

const UserDelete: React.FC<UserDeleteProps> = props => {
  const [removeUser, { loading }] = useMutation<
    UserRemove,
    UserRemoveVariables
  >(USER_REMOVE);
  const { enqueueSnackbar } = useSnackbar();
  const [modal, setModal] = useState(false);
  const router = useRouter();
  const canEdit = useResources([resources.users.edit]);

  const submitHandler = (): void => {
    removeUser({
      variables: {
        id: +router.query.userId,
      },
    })
      .then(() => {
        enqueueSnackbar('Uživatel úspěšně odstraněn', { variant: 'success' });
        router.push(routes.users.index);
      })
      .catch(() => {
        enqueueSnackbar('Uživatele se nepovedlo odstranit', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <Typography variant="h6">Odstranit uživatele</Typography>
      <Typography>
        Na vždy odstraníte uživatele ze systému. Lze použít pouze pokud nebyl
        uživatel nikdy aktivován.
      </Typography>

      <Box display="flex" justifyContent="flex-end">
        <LoadingButton
          disabled={props.active || props.approved || !canEdit}
          loading={loading}
          color="secondary"
          variant="contained"
          onClick={() => {
            setModal(true);
          }}
        >
          Odstranit uživatele
        </LoadingButton>
      </Box>
      <Dialog open={modal}>
        <DialogTitle>Doopravdy chcete uživatele odstranit??!!</DialogTitle>
        <DialogActions>
          <LoadingButton
            color="primary"
            loading={loading}
            onClick={submitHandler}
          >
            Odstranit
          </LoadingButton>
          <LoadingButton
            color="secondary"
            loading={loading}
            onClick={() => setModal(false)}
          >
            Zrušit
          </LoadingButton>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default UserDelete;
