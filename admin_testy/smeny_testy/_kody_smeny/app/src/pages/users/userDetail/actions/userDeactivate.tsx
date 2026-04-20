import { useMutation } from '@apollo/react-hooks';
import { makeStyles, Typography } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import React from 'react';

import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import {
  UserActivateDeactivateProps,
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
    userChangeActive(userId: $userId, active: false) {
      id
      active
    }
  }
`;

const UserDeactivate: React.FC<UserActivateDeactivateProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const [userChangeActive, { loading }] = useMutation<
    UserChangeActive,
    UserChangeActiveVars
  >(USER_CHANGE_ACTIVE);
  const canEdit = useResources([resources.users.edit]);

  const submitHandler = (): void => {
    userChangeActive({ variables: { userId: +router.query.userId } })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Uživatel úspěšně deaktivován', {
            variant: 'success',
          });
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Nepovedlo se deaktivovat uživatele', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <Typography variant="h6">Deaktivovat uživatele</Typography>
      <Typography>
        Touto akcí zablokujete uživateli přístup do systému. Všechna jeho
        historie však zůstane zachována. Po této akci jde uživatel znovu
        aktivovat.
      </Typography>

      <div className={classes.buttonContainer}>
        <LoadingButton
          disabled={!props.active || !canEdit}
          loading={loading}
          color="secondary"
          variant="contained"
          onClick={submitHandler}
        >
          Deaktivovat uživatele
        </LoadingButton>
      </div>
    </>
  );
};

export default withSnackbar(UserDeactivate);
