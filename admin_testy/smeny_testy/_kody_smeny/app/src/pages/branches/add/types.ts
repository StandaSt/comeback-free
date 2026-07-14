import { WithSnackbarProps } from 'notistack';

export interface FormTypes {
  name: string;
}

export interface BranchCreate {
  branchCreate: {
    id: number;
    name: string;
  };
}

export interface BranchCreateVars {
  name: string;
  color: string;
}

export type AddProps = WithSnackbarProps;
