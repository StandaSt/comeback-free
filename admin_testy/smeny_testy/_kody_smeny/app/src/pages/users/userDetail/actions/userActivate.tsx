import { useMutation } from '@apollo/react-hooks';
import {
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  makeStyles,
  Typography,
} from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useState } from 'react';

import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import {
  UserActivateProps,
  UserChangeActive,
  UserChangeActiveVars,
} from './types';

const useStyles = makeStyles({
  buttonContainer: {
    display: 'grid',
    justifyItems: 'right',
  },
});
const USER_CHANGE_ACTIVE = gql`
  mutation($userId: Int!) {
    userChangeActive(userId: $userId, active: true) {
      id
      active
      approved
    }
  }
`;

const UserActivate: React.FC<UserActivateProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const [userChangeActive, { loading }] = useMutation<
    UserChangeActive,
    UserChangeActiveVars
  >(USER_CHANGE_ACTIVE);
  const [modal, setModal] = useState(false);
  const canEdit = useResources([resources.users.edit]);

  const submitHandler = (): void => {
    userChangeActive({ variables: { userId: +router.query.userId } })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Uživatel úspěšně aktivován', {
            variant: 'success',
          });
          setModal(false);
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Nepovedlo se aktivovat uživatele', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <Typography variant="h6">Aktivovat uživatele</Typography>
      <Typography>Touto akcí obnovíte uživateli přístup do systému.</Typography>

      <div className={classes.buttonContainer}>
        <LoadingButton
          disabled={props.active || !canEdit}
          loading={loading}
          color="primary"
          variant="contained"
          onClick={() => {
            if (props.approved) {
              submitHandler();
            } else {
              setModal(true);
            }
          }}
        >
          Aktivovat uživatele
        </LoadingButton>
      </div>
      <Dialog open={modal}>
        <DialogTitle>Chcete aktivovat uživatele?</DialogTitle>
        <DialogContent>
          <Typography>Již nebudete schopni uživatel odstranit</Typography>
        </DialogContent>
        <DialogActions>
          <LoadingButton
            color="primary"
            loading={loading}
            onClick={submitHandler}
          >
            Aktivovat
          </LoadingButton>
          <LoadingButton
            color="secondary"
            loading={loading}
            onClick={() => {
              setModal(false);
            }}
          >
            Zrušit
          </LoadingButton>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default withSnackbar(UserActivate);
