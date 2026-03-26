import { useMutation, useQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useState } from 'react';
import { Chip, Grid, IconButton, Menu, MenuItem } from '@material-ui/core';
import AddIcon from '@material-ui/icons/Add';

import MaterialTable from 'lib/materialTable';
import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import planableBranchesFragment from '../../fragments/planableBranchesFragment';
import { PlanableBranchesProps } from '../types';
import planableShiftRoleTypes from '../../fragments/planableShiftRoleTypes';

import {
  BranchFindAll,
  UserChangePlanableBranches,
  UserChangePlanableBranchesVars,
  UserChangePlanableShiftRoleTypes,
  UserChangePlanableShiftRoleTypesVariables,
} from './types';

const BRANCH_FIND_ALL = gql`
  {
    branchFindAll {
      id
      name
    }
    shiftRoleTypeFindAll {
      id
      name
    }
  }
`;

const USER_CHANGE_PLANABLE_BRANCHES = gql`
  ${planableBranchesFragment}
  mutation($userId: Int!, $branchesIds: [Int!]!) {
    userChangePlanableBranches(userId: $userId, branchIds: $branchesIds) {
      id
      ...PlanableBranches
    }
  }
`;

const USER_CHANGE_PLANABLE_SHIFT_ROLE_TYPES = gql`
  ${planableShiftRoleTypes}
  mutation($userId: Int!, $shiftRoleTypeIds: [Int!]!) {
    userChangePlanableShiftRoleTypes(
      userId: $userId
      shiftRoleTypeIds: $shiftRoleTypeIds
    ) {
      id
      ...PlanableShiftRoleTypes
    }
  }
`;

const PlanableBranches: React.FC<PlanableBranchesProps> = props => {
  const { data, loading } = useQuery<BranchFindAll>(BRANCH_FIND_ALL);
  const [userChangeBranches, { loading: mutationLoading }] = useMutation<
    UserChangePlanableBranches,
    UserChangePlanableBranchesVars
  >(USER_CHANGE_PLANABLE_BRANCHES);
  const [
    userChangePlanableShiftRoleTypes,
    { loading: planableShiftRoleTypesLoading },
  ] = useMutation<
    UserChangePlanableShiftRoleTypes,
    UserChangePlanableShiftRoleTypesVariables
  >(USER_CHANGE_PLANABLE_SHIFT_ROLE_TYPES);
  const [selected, setSelected] = useState(props.planableBranches);
  const [menuAnchorEl, setMenuAnchorEl] = useState<null | HTMLElement>(null);

  const canEdit = useResources([resources.users.edit]);

  const submitHandler = (): void => {
    const branchesIds = selected.map(s => s.id);
    userChangeBranches({ variables: { userId: props.userId, branchesIds } })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Pobočky úspěšně změněny', {
            variant: 'success',
          });
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Nepovedlo se změnit pobočky', {
          variant: 'error',
        });
      });
  };

  const shiftRoleTypeDeleteHandler = (id: number): void => {
    const shiftRoleTypesIds = props.planableShiftRoleTypes
      .filter(t => t.id !== id)
      .map(t => t.id);
    userChangePlanableShiftRoleTypes({
      variables: { userId: props.userId, shiftRoleTypeIds: shiftRoleTypesIds },
    })
      .then(() => {
        props.enqueueSnackbar('Typ slotu úspěšně odebrán', {
          variant: 'success',
        });
      })
      .catch(() => {
        props.enqueueSnackbar('Nepovedlo se odebrat typ slotu', {
          variant: 'error',
        });
      });
  };

  const planableShiftRoleTypeCloseHandler = (): void => {
    setMenuAnchorEl(null);
  };

  const planableShiftRoleTypeAddHandler = (id: number): void => {
    userChangePlanableShiftRoleTypes({
      variables: {
        userId: props.userId,
        shiftRoleTypeIds: [...props.planableShiftRoleTypes.map(t => t.id), id],
      },
    })
      .then(() => {
        props.enqueueSnackbar('Typ slotu úspěšně přidán', {
          variant: 'success',
        });
        planableShiftRoleTypeCloseHandler();
      })
      .catch(() => {
        props.enqueueSnackbar('Nepoveldo se přidat typ slotu', {
          variant: 'error',
        });
      });
  };

  const mappedShiftRoleTypes = props.planableShiftRoleTypes.map(type => (
    <Grid item key={type.id}>
      <Chip
        color="primary"
        disabled={!canEdit}
        onDelete={() => shiftRoleTypeDeleteHandler(type.id)}
        label={type.name}
      />
    </Grid>
  ));

  const mappedAllShiftRoleTypes = data?.shiftRoleTypeFindAll.map(type => (
    <MenuItem
      key={type.id}
      onClick={() => planableShiftRoleTypeAddHandler(type.id)}
      disabled={planableShiftRoleTypesLoading}
    >
      {type.name}
    </MenuItem>
  ));

  const fetchedBranches = data?.branchFindAll.map(branch => {
    const checked = selected.some(b => b.id === branch.id);

    return {
      tableData: { checked },
      ...branch,
    };
  });

  return (
    <>
      <Grid container spacing={2}>
        <Grid item container xs={12} spacing={2} alignItems="center">
          {mappedShiftRoleTypes}
          <Grid item>
            <IconButton
              color="primary"
              onClick={e => setMenuAnchorEl(e.currentTarget)}
              disabled={planableShiftRoleTypesLoading || !canEdit}
            >
              <AddIcon />
            </IconButton>
          </Grid>
        </Grid>
        <Menu anchorEl={menuAnchorEl} open={Boolean(menuAnchorEl)} keepMounted>
          {mappedAllShiftRoleTypes}
        </Menu>
        <Grid item xs={12}>
          <MaterialTable
            isLoading={loading || props.loading}
            columns={[{ title: 'Název', field: 'name' }]}
            data={fetchedBranches}
            options={{ selection: canEdit }}
            onSelectionChange={d => setSelected(d)}
          />
        </Grid>
      </Grid>
      <Actions
        actions={[
          {
            id: 2,
            element: (
              <LoadingButton
                loading={mutationLoading}
                color="primary"
                variant="contained"
                onClick={submitHandler}
                disabled={!canEdit}
              >
                Uložit
              </LoadingButton>
            ),
          },
          {
            id: 3,
            element: (
              <LoadingButton
                loading={mutationLoading}
                color="secondary"
                variant="contained"
                onClick={() => {
                  setSelected(props.planableBranches);
                }}
                disabled={!canEdit}
              >
                Zrušit
              </LoadingButton>
            ),
          },
        ]}
      />
    </>
  );
};

export default withSnackbar(PlanableBranches);
