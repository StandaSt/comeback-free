import { useMutation } from '@apollo/react-hooks';
import { Typography } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import React from 'react';

import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import { ActivateProps, BranchActivate, BranchActivateVars } from './types';

const BRANCH_ACTIVATE = gql`
  mutation($id: Int!) {
    branchActivate(id: $id, active: true) {
      id
      active
    }
  }
`;

const Activate = (props: ActivateProps) => {
  const [branchActivate, { loading }] = useMutation<
    BranchActivate,
    BranchActivateVars
  >(BRANCH_ACTIVATE);

  const canEdit = useResources([resources.branches.edit]);

  const { enqueueSnackbar } = useSnackbar();

  const submitHandler = () => {
    branchActivate({ variables: { id: props.branchId } })
      .then(() => {
        enqueueSnackbar('Pobočka úspěšně aktivována', { variant: 'success' });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se aktivovat pobočku', { variant: 'error' });
      });
  };

  return (
    <>
      <Typography variant="h6">Aktivovat</Typography>
      <Typography>
        Aktivuje pobočku. Bude možné znovu plánovat směny pro pobočku.
      </Typography>
      <Actions
        actions={[
          {
            id: 0,
            element: (
              <LoadingButton
                disabled={props.active || !canEdit}
                loading={loading}
                color="primary"
                variant="contained"
                onClick={submitHandler}
              >
                Aktivovat
              </LoadingButton>
            ),
          },
        ]}
      />
    </>
  );
};

export default Activate;
