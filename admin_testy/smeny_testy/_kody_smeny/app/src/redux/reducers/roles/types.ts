export interface Role {
  id: number;
  name: string;
  sortIndex: number;
}

export interface Resource {
  id: number;
  name: string;
  label: string;
  roles: { id: number }[];
  minimalCount: number;
  requires: { id: number }[];
}

export interface ChangedResource {
  resourceId: number;
  roleId: number;
  active: boolean;
}
export interface ResourceCategory {
  id: number;
  name: string;
  label: string;
  resources: Resource[];
}

export interface RolesReducer {
  resourceCategories: ResourceCategory[];
  roles: Role[];
  changedResources: ChangedResource[];
}
