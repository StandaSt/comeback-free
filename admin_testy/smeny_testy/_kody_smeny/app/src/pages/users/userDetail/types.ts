import { WithSnackbarProps } from 'notistack';

export interface Role {
  id: number;
  name: string;
  resources: { id: number; name: string }[];
}

interface PlanableShiftRoleType {
  id: number;
  name: string;
}

interface PlanableBranch {
  id: number;
  name: string;
}

interface WorkingBranch {
  id: number;
  name: string;
}

interface WorkersShiftRoleType {
  id: number;
  name: string;
}

export interface MainBranch {
  id: number;
  name: string;
}

export interface User {
  id: number;
  email: string;
  name: string;
  surname: string;
  createTime: Date;
  lastLoginTime: Date;
  roles: Role[];
  active: boolean;
  planableShiftRoleTypes: PlanableShiftRoleType[];
  planableBranches: PlanableBranch[];
  workingBranches: WorkingBranch[];
  workersShiftRoleTypes: WorkersShiftRoleType[];
  mainBranch: MainBranch;
  hasOwnCar: boolean;
  phoneNumber: string;
  notificationsActivated: boolean;
  receiveEmails: boolean;
}

export interface UserFindById {
  userFindById: User;
}

export interface UserFindByIdVars {
  id: number;
}

export interface UserChangeRoles {
  userChangeRoles: {
    id: number;
    roles: Role[];
  };
}

export interface UserChangeRolesVars {
  userId: number;
  rolesIds: number[];
}

export interface RoleFindAll {
  roleFindAll: Role[];
}

export interface RolesProps extends WithSnackbarProps {
  roles: Role[];
  loading: boolean;
}

export interface PlanableBranchesProps extends WithSnackbarProps {
  userId: number;
  planableBranches: PlanableBranch[];
  planableShiftRoleTypes: PlanableShiftRoleType[];
  loading: boolean;
}
