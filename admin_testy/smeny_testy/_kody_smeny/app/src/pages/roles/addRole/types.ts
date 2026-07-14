import { WithSnackbarProps } from 'notistack';
import { ConnectedComponent } from 'react-redux';

import { Role } from 'redux/reducers/roles/types';

export interface RoleCreate {
  roleCreate: {
    id: number;
    name: string;
    sortIndex: number;
    roles: {
      id: number;
      name: string;
    };
  };
}

export interface RoleCreateVars {
  name: string;
  maxUsers: number;
  registrationDefault: boolean;
}

export interface MapDispatch {
  addRole: (role: Role) => void;
}

export interface AddRoleProps
  extends WithSnackbarProps,
    MapDispatch,
    ConnectedComponent<any, any> {}
