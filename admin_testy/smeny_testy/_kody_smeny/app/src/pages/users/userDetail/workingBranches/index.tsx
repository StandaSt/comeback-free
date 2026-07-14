import { useMutation, useQuery } from '@apollo/react-hooks';
import { Chip, Grid, IconButton, Menu, MenuItem } from '@material-ui/core';
import AddIcon from '@material-ui/icons/Add';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import React, { useState } from 'react';
import resources from '@shift-planner/shared/config/api/resources';

import MaterialTable from 'lib/materialTable';
import mainBranchFragment from 'pages/users/fragments/mainBranchFragment';
import workersShiftRoleTypesFragment from 'pages/users/fragments/workersShiftRoleTypesFragment';
import workingBranchesFragment from 'pages/users/fragments/workingBranchesFragment';
import MainBranch from 'pages/users/userDetail/workingBranches/mainBranch';
import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import {
  BranchFindAll,
  UserChangeWorkersShiftRoleTypes,
  UserChangeWorkersShiftRoleTypesVars,
  UserChangeWorkingBranches,
  UserChangeWorkingBranchesVars,
  WorkingBranch,
  WorkingBranchesProps,
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

const USER_CHANGE_WORKING_BRANCHES = gql`
  ${workingBranchesFragment}
  ${mainBranchFragment}
  mutation($userId: Int!, $branchIds: [Int!]!) {
    userChangeWorkingBranches(userId: $userId, branchIds: $branchIds) {
      id
      ...WorkingBranches
      ...MainBranch
    }
  }
`;

const USER_CHANGE_WORKERS_SHIFT_ROLE_TYPES = gql`
  ${workersShiftRoleTypesFragment}
  mutation($userId: Int!, $shiftRoleTypeIds: [Int!]!) {
    userChangeWorkersShiftRoleTypes(
      userId: $userId
      shiftRoleTypeIds: $shiftRoleTypeIds
    ) {
      id
      ...WorkersShiftRoleTypes
    }
  }
`;

const WorkingBranches: React.FC<WorkingBranchesProps> = props => {
  const [selected, setSelected] = useState<WorkingBranch[]>(
    props.workingBranches,
  );
  const [menuAnchorEl, setMenuAnchorEl] = useState(null);
  const { enqueueSnackbar } = useSnackbar();

  const { data, loading } = useQuery<BranchFindAll>(BRANCH_FIND_ALL);
  const [userChangeWorkingBranches, { loading: branchLoading }] = useMutation<
    UserChangeWorkingBranches,
    UserChangeWorkingBranchesVars
  >(USER_CHANGE_WORKING_BRANCHES);
  const [
    userChangeWorkersShiftRoleTypes,
    { loading: typeLoading },
  ] = useMutation<
    UserChangeWorkersShiftRoleTypes,
    UserChangeWorkersShiftRoleTypesVars
  >(USER_CHANGE_WORKERS_SHIFT_ROLE_TYPES);

  const canEdit = useResources([resources.users.edit]);

  const fetchedData = data?.branchFindAll.map(branch => {
    const checked = selected.some(b => b.id === branch.id);

    return {
      tableData: { checked },
      ...branch,
    };
  });

  const submitHandler = (): void => {
    const branchIds = selected.map(s => s.id);

    userChangeWorkingBranches({
      variables: { userId: props.userId, branchIds },
    })
      .then(() => {
        enqueueSnackbar('Pobočky úspěšně změneny', { variant: 'success' });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se změnit pobočky', { variant: 'error' });
      });
  };

  const shiftRoleTypeOpenHandler = (
    e: React.MouseEvent<HTMLButtonElement>,
  ): void => {
    setMenuAnchorEl(e.currentTarget);
  };

  const shiftRoleTypeCloseHandler = (): void => {
    setMenuAnchorEl(null);
  };

  const changeShiftRoleTypes = (ids: number[]): void => {
    userChangeWorkersShiftRoleTypes({
      variables: { userId: props.userId, shiftRoleTypeIds: ids },
    })
      .then(() => {
        enqueueSnackbar('Typy směn úspěšně upraveny', { variant: 'success' });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se upravit typy směn', { variant: 'error' });
      });
  };

  const shiftRoleTypeAddHandler = (id: number): void => {
    const oldIds = props.workersShiftRoleTypes.map(t => t.id);
    changeShiftRoleTypes([...oldIds, id]);
    shiftRoleTypeCloseHandler();
  };

  const shiftRoleTypeRemoveHandler = (id: number): void => {
    let oldIds = props.workersShiftRoleTypes.map(t => t.id);
    oldIds = oldIds.filter(v => v !== id);
    changeShiftRoleTypes([...oldIds]);
  };

  const mappedShiftRoleTypes = props.workersShiftRoleTypes.map(type => (
    <Grid item key={type.id}>
      <Chip
        color="primary"
        disabled={!canEdit}
        onDelete={() => shiftRoleTypeRemoveHandler(type.id)}
        label={type.name}
      />
    </Grid>
  ));

  const mappedAllShiftRoleTypes = data?.shiftRoleTypeFindAll.map(type => (
    <MenuItem key={type.id} onClick={() => shiftRoleTypeAddHandler(type.id)}>
      {type.name}
    </MenuItem>
  ));

  return (
    <>
      <Grid container spacing={2}>
        <Grid item container xs={12} spacing={2} alignItems="center">
          {mappedShiftRoleTypes}
          <Grid item>
            <IconButton
              color="primary"
              onClick={shiftRoleTypeOpenHandler}
              disabled={typeLoading || !canEdit}
            >
              <AddIcon />
            </IconButton>
          </Grid>
        </Grid>

        <Menu
          anchorEl={menuAnchorEl}
          keepMounted
          open={Boolean(menuAnchorEl)}
          onClose={shiftRoleTypeCloseHandler}
        >
          {mappedAllShiftRoleTypes}
        </Menu>
        <Grid item xs={12}>
          <MainBranch
            branches={props.workingBranches}
            mainBranch={props.mainBranch}
            userId={props.userId}
          />
        </Grid>
        <Grid item xs={12}>
          <MaterialTable
            isLoading={props.loading || loading}
            data={fetchedData}
            columns={[{ title: 'Název', field: 'name' }]}
            options={{ selection: canEdit, filtering: true }}
            onSelectionChange={d => {
              setSelected(d);
            }}
          />
        </Grid>
      </Grid>
      <Actions
        actions={[
          {
            id: 0,
            element: (
              <LoadingButton
                color="primary"
                variant="contained"
                onClick={submitHandler}
                loading={branchLoading}
                disabled={!canEdit}
              >
                Uložit
              </LoadingButton>
            ),
          },
          {
            id: 1,
            element: (
              <LoadingButton
                color="secondary"
                variant="contained"
                onClick={() => {
                  setSelected(props.workingBranches);
                }}
                disabled={!canEdit}
                loading={branchLoading}
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

export default WorkingBranches;
