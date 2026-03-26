import { useMutation, useQuery } from '@apollo/react-hooks';
import { Typography } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import { Dispatch } from 'redux';
import React, { useState } from 'react';
import { connect } from 'react-redux';
import resources from '@shift-planner/shared/config/api/resources';

import rolesBreadcrumbs from 'pages/roles/index/breadcrumbs';
import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import withPage from 'components/withPage';
import {
  rolesAddChangedResource,
  rolesChangeResourceCategories,
  rolesChangeRoles,
  rolesClearChangedResources,
  rolesUpdateResources,
} from 'redux/actions/roles';
import {
  ChangedResource,
  Resource,
  ResourceCategory,
  Role,
} from 'redux/reducers/roles/types';
import { State } from 'redux/reducers/types';

import rolesResources from './resources';
import Roles from './roles';
import {
  MapDispatch,
  MapState,
  ResourceChangeRoles,
  ResourceChangeRolesVars,
  ResourceRoleFindAll,
  RolesIndexProps,
} from './types';

const RESOURCE_CHANGE_ROLES = gql`
  mutation($changedRoles: [ChangedRoleArg!]!) {
    resourceChangeRoles(changedRoles: $changedRoles) {
      id
      name
      label
      roles {
        id
      }
      minimalCount
      requires {
        id
      }
    }
  }
`;

const RESOURCE_CATEGORY_FIND_ALL = gql`
  {
    resourceCategoryFindAll {
      id
      name
      label
      resources {
        id
        name
        label
        roles {
          id
        }
        minimalCount
        requires {
          id
        }
      }
    }
    roleFindAll {
      id
      name
      sortIndex
    }
  }
`;

const RolesIndex: React.FC<RolesIndexProps> = props => {
  const { data, error, loading } = useQuery<ResourceRoleFindAll>(
    RESOURCE_CATEGORY_FIND_ALL,
    {
      fetchPolicy: 'no-cache',
    },
  );
  const [
    resourceChangeRoles,
    { data: mutationData, loading: mutationLoading, error: mutationError },
  ] = useMutation<ResourceChangeRoles, ResourceChangeRolesVars>(
    RESOURCE_CHANGE_ROLES,
  );
  const [saved, setSaved] = useState(false);
  const [mutationSnacked, setMutationSnacked] = useState(false);
  const canEditResources = useResources([resources.roles.editResources]);

  if (error) {
    props.enqueueSnackbar('Něco se pokazilo', { variant: 'error' });
  }

  if (data && !saved) {
    props.rolesChangeResourceCategories(data.resourceCategoryFindAll);
    props.rolesChangeRoles(data.roleFindAll);
    setSaved(true);
  }

  if (mutationData && !mutationSnacked) {
    setMutationSnacked(true);
    props.rolesClearChangedResources();
    props.rolesUpdateResources(mutationData.resourceChangeRoles);
    props.enqueueSnackbar('Role úspěšně aktualizovány', { variant: 'success' });
  } else if (mutationError && !mutationSnacked) {
    setMutationSnacked(true);
    props.enqueueSnackbar('Nepovedlo se aktualizovat role', {
      variant: 'error',
    });
  }

  const changeResourceHandler = (
    resourceId: number,
    roleId: number,
    active: boolean,
  ): void => {
    props.rolesAddChangedResource({ resourceId, roleId, active });
  };
  const cancelHandler = (): void => {
    props.rolesClearChangedResources();
  };

  const submitHandler = (): void => {
    setMutationSnacked(false);
    resourceChangeRoles({
      variables: { changedRoles: props.changedResources },
    });
  };

  const sortedRoles = props.roles.sort((role1, role2) => {
    if (role1.sortIndex > role2.sortIndex) {
      return 1;
    }
    if (role1.sortIndex < role2.sortIndex) {
      return -1;
    }

    return 0;
  });

  return (
    <>
      <Paper
        loading={loading}
        title="Role"
        actions={[
          <LoadingButton
            loading={mutationLoading}
            key="actionSave"
            variant="contained"
            color="primary"
            onClick={submitHandler}
            disabled={!canEditResources}
          >
            Uložit
          </LoadingButton>,
          <LoadingButton
            loading={mutationLoading}
            key="actionCancel"
            variant="contained"
            color="secondary"
            disabled={!canEditResources}
            onClick={cancelHandler}
          >
            Zrušit
          </LoadingButton>,
        ]}
        footer={
          <Typography>{`Počet změn: ${props.changedResources.length}`}</Typography>
        }
      >
        <Roles
          resourceCategories={props.resourceCategories}
          roles={sortedRoles}
          changedResources={props.changedResources}
          onResourceChange={changeResourceHandler}
        />
      </Paper>
    </>
  );
};

const mapStateToProps = (state: State): MapState => ({
  roles: state.roles.roles,
  resourceCategories: state.roles.resourceCategories,
  changedResources: state.roles.changedResources,
});

const mapDispatchToProps = (dispatch: Dispatch): MapDispatch => ({
  rolesChangeRoles: (roles: Role[]) => dispatch(rolesChangeRoles(roles)),
  rolesChangeResourceCategories: (resourceCategories: ResourceCategory[]) =>
    dispatch(rolesChangeResourceCategories(resourceCategories)),
  rolesAddChangedResource: (changedResource: ChangedResource) =>
    dispatch(rolesAddChangedResource(changedResource)),
  rolesClearChangedResources: () => dispatch(rolesClearChangedResources()),
  rolesUpdateResources: (r: Resource[]) => dispatch(rolesUpdateResources(r)),
});

export default withPage(
  connect(mapStateToProps, mapDispatchToProps)(withSnackbar(RolesIndex)),
  rolesBreadcrumbs,
  rolesResources,
);
