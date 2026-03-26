import rolesActionTypes from 'redux/reducers/roles/actionTypes';

import { ChangedResource, Resource, RolesReducer } from './types';

const initState: RolesReducer = {
  resourceCategories: [],
  roles: [],
  changedResources: [],
};

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
const rolesReducer = (s = initState, action: any): RolesReducer => {
  const { type } = action;

  const state = { ...s };

  if (type === rolesActionTypes.changeResourceCategories) {
    return { ...state, resourceCategories: action.resourceCategories };
  }
  if (type === rolesActionTypes.changeRoles) {
    return { ...state, roles: action.roles };
  }
  if (type === rolesActionTypes.addChangedResource) {
    const { changedResource }: { changedResource: ChangedResource } = action;
    const changedResources = [...state.changedResources];
    const oldResourceIndex = changedResources.findIndex(
      r =>
        r.resourceId === changedResource.resourceId &&
        r.roleId === changedResource.roleId,
    );

    if (oldResourceIndex >= 0) {
      changedResources.splice(oldResourceIndex, 1);
    } else if (oldResourceIndex < 0) {
      changedResources.push(changedResource);
    }

    return { ...state, changedResources };
  }
  if (type === rolesActionTypes.clearChangedResource) {
    return { ...state, changedResources: [] };
  }
  if (type === rolesActionTypes.updateResources) {
    const updatedResources: Resource[] = action.resources;

    updatedResources.forEach(updated => {
      const category = state.resourceCategories.find(c =>
        c.resources.some(r => r.id === updated.id),
      );
      if (category) {
        const resource = category.resources.find(r => r.id === updated.id);
        if (resource) {
          resource.roles = updated.roles;
        }
      }
    });
  }
  if (type === rolesActionTypes.addRole) {
    state.roles.push(action.role);

    return { ...state };
  }
  if (type === rolesActionTypes.removeRole) {
    const roles = [...state.roles];
    const roleIndex = roles.findIndex(r => r.id === action.id);
    if (roleIndex >= 0) roles.splice(roleIndex, 1);

    return { ...state, roles };
  }

  return state;
};

export default rolesReducer;
