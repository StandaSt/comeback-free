import { WithSnackbarProps } from 'notistack';

export interface Planner {
  id: number;
  name: string;
  surname: string;
}

export interface Worker {
  id: number;
  name: string;
  surname: string;
}

export interface BranchFindById {
  branchFindById: {
    id: number;
    name: string;
    color: string;
    active: boolean;
    planners: Planner[];
    workers: Worker[];
  };
}

export interface BasicInfoProps extends WithSnackbarProps {
  name: string;
  active: boolean;
  color: string;
  id: number;
  loading: boolean;
}

export interface BasicInfoFormTypes {
  name: string;
}

export interface BranchEdit {
  branchEdit: {
    id: number;
    name: string;
    color: string;
  };
}

export interface BranchEditVars {
  id: number;
  name: string;
  color: string;
}
