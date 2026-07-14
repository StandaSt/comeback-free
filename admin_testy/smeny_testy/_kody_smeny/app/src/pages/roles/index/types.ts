import { WithSnackbarProps } from 'notistack';
import { ConnectedComponent } from 'react-redux';

import {
  ChangedResource,
  Resource as ResourceRedux,
  ResourceCategory,
  Role as RoleRedux,
} from 'redux/reducers/roles/types';

interface Role {
  id: number;
  name: string;
  sortIndex: number;
}

interface Resource {
  id: number;
  name: string;
  label: string;
  roles: { id: number }[];
  minimalCount: number;
  requires: { id: number }[];
}

interface Category {
  id: number;
  name: string;
  label: string;
  resources: Resource[];
}

export interface ResourceRoleFindAll {
  resourceCategoryFindAll: Category[];
  roleFindAll: Role[];
}

export interface ResourceChangeRoles {
  resourceChangeRoles: {
    id: number;
    name: string;
    label: string;
    roles: {
      id: number;
    }[];
    minimalCount: number;
    requires: { id: number }[];
  }[];
}

export interface ResourceChangeRolesVars {
  changedRoles: {
    active: boolean;
    resourceId: number;
    roleId: number;
  }[];
}

export interface MapState {
  roles: RoleRedux[];
  resourceCategories: ResourceCategory[];
  changedResources: ChangedResource[];
}

export interface MapDispatch {
  rolesChangeRoles: (roles: RoleRedux[]) => void;
  rolesChangeResourceCategories: (roles: ResourceCategory[]) => void;
  rolesAddChangedResource: (changedResource: ChangedResource) => void;
  rolesClearChangedResources: () => void;
  rolesUpdateResources: (resources: ResourceRedux[]) => void;
}

export interface RolesIndexProps
  extends MapState,
    MapDispatch,
    WithSnackbarProps,
    ConnectedComponent<any, any> {}

export interface RolesProps extends WithSnackbarProps {
  resourceCategories: ResourceCategory[];
  roles: RoleRedux[];
  changedResources: ChangedResource[];
  onResourceChange: (
    resourceId: number,
    roleId: number,
    active: boolean,
  ) => void;
}
