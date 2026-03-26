import { WithSnackbarProps } from 'notistack';

export interface ShiftRoleType {
  id: number;
  name: string;
  registrationDefault: boolean;
  sortIndex: number;
  color: string;
  useCars: boolean;
}

export interface ShiftRoleTypeFindAll {
  shiftRoleTypeFindAll: ShiftRoleType[];
}

export interface ShiftRoleTypeDeactivate {
  shiftRoleTypeDeactivate: ShiftRoleType[];
}

export interface ShiftRoleTypeDeactivateVars {
  id: number;
}

export type ShiftRoleTypeIndexProps = WithSnackbarProps;
