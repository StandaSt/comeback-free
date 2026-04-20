import { WithSnackbarProps } from 'notistack';

export interface FormTypes {
  name: string;
  registrationDefault: boolean;
  sortIndex: number;
  color: string;
}

export interface ShiftRoleTypeCreate {
  shiftRoleTypeCreate: {
    id: number;
    name: string;
    registrationDefault: boolean;
    sortIndex: number;
    color: string;
  };
}

export interface ShiftRoleTypeCreateVars {
  name: string;
  registrationDefault: boolean;
  sortIndex: number;
  color: string;
}

export type AddProps = WithSnackbarProps;
