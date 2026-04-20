import rolesActionTypes from 'redux/reducers/roles/actionTypes';
import {
  ChangedResource,
  Resource,
  ResourceCategory,
  Role,
} from 'redux/reducers/roles/types';

export const rolesChangeResourceCategories = (
  resourceCategories: ResourceCategory[],
): any => ({
  type: rolesActionTypes.changeResourceCategories,
  resourceCategories,
});

export const rolesChangeRoles = (roles: Role[]): any => ({
  type: rolesActionTypes.changeRoles,
  roles,
});

export const rolesAddChangedResource = (
  changedResource: ChangedResource,
): any => ({
  type: rolesActionTypes.addChangedResource,
  changedResource,
});

export const rolesClearChangedResources = (): any => ({
  type: rolesActionTypes.clearChangedResource,
});

export const rolesUpdateResources = (resources: Resource[]): any => ({
  type: rolesActionTypes.updateResources,
  resources,
});

export const rolesAddRole = (role: Role): any => ({
  type: rolesActionTypes.addRole,
  role,
});

export const rolesRemoveRole = (id: number): any => ({
  type: rolesActionTypes.removeRole,
  id,
});
