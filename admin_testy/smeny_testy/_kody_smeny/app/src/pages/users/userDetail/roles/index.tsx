import { useMutation, useQuery } from '@apollo/react-hooks';
import { Theme } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import apiErrors from '@shift-planner/shared/config/api/errors';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useState } from 'react';

import MaterialTable from 'lib/materialTable';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import roleFragment from '../../fragments/roleFragment';
import {
  Role,
  RoleFindAll,
  RolesProps,
  UserChangeRoles,
  UserChangeRolesVars,
} from '../types';

const useStyles = makeStyles((theme: Theme) => ({
  actions: {
    paddingTop: theme.spacing(2),
    display: 'grid',
    justifyItems: 'end',
    gridTemplateColumns: '1fr auto',
  },
  action: {
    display: 'table',
    marginLeft: theme.spacing(2),
  },
}));

const ROLE_FIND_ALL = gql`
  {
    roleFindAll {
      id
      name
    }
  }
`;

const USER_CHANGE_ROLES = gql`
  ${roleFragment}
  mutation($userId: Int!, $rolesIds: [Int!]!) {
    userChangeRoles(userId: $userId, rolesIds: $rolesIds) {
      id
      ...Roles
    }
  }
`;

const Index: React.FC<RolesProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const { data, loading } = useQuery<RoleFindAll>(ROLE_FIND_ALL);
  const [userChangeRoles, { loading: muationLoading }] = useMutation<
    UserChangeRoles,
    UserChangeRolesVars
  >(USER_CHANGE_ROLES);
  const [selected, setSelected] = useState<Role[]>(props.roles);
  const canEdit = useResources([resources.users.edit]);

  const submitHandler = (): void => {
    const rolesIds = selected.map(s => s.id);
    userChangeRoles({
      variables: { userId: +router.query.userId, rolesIds },
    })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Role úspěšně změněny', { variant: 'success' });
        }
      })
      .catch(err => {
        if (
          err?.graphQLErrors?.some(
            e => e.message?.message === apiErrors.role.maxUsers,
          )
        ) {
          props.enqueueSnackbar(
            'Maximální počet uživatelů jedné z rolí byl překročen.',
            {
              variant: 'warning',
            },
          );
        } else
          props.enqueueSnackbar('Nepovedlo se změnit role', {
            variant: 'error',
          });
      });
  };

  const fetchedRoles = data
    ? data.roleFindAll.map(role => {
        const checked = selected.some(r => r.id === role.id);

        return {
          tableData: { checked },
          ...role,
        };
      })
    : [];

  return (
    <>
      <MaterialTable
        isLoading={loading || props.loading}
        columns={[{ title: 'Název', field: 'name' }]}
        data={fetchedRoles}
        options={{ selection: canEdit, filtering: true }}
        onSelectionChange={d => {
          setSelected(d);
        }}
      />
      <div className={classes.actions}>
        <div className={classes.action}>
          <LoadingButton
            loading={muationLoading}
            color="primary"
            variant="contained"
            onClick={submitHandler}
            disabled={!canEdit}
          >
            Uložit
          </LoadingButton>
        </div>
        <div className={classes.action}>
          <LoadingButton
            loading={muationLoading}
            color="secondary"
            variant="contained"
            onClick={() => {
              setSelected(props.roles);
            }}
            disabled={!canEdit}
          >
            Zrušit
          </LoadingButton>
        </div>
      </div>
    </>
  );
};

export default withSnackbar(Index);
