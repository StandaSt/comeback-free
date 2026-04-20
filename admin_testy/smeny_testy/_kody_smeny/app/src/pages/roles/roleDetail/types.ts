import { WithSnackbarProps } from 'notistack';

interface Role {
  id: number;
  name: string;
  maxUsers: number;
  userCount: number;
  registrationDefault: boolean;
  sortIndex: number;
}

export interface RoleFindById {
  roleFindById: Role;
}

export interface RoleFindByIdVars {
  id: number;
}

export interface MapDispatch {
  removeRole: (id: number) => void;
}

export interface BasicInfoProps extends WithSnackbarProps {
  role: Role;
  loading: boolean;
}
