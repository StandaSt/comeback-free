import { MainBranch } from '../types';

export interface WorkingBranch {
  id: number;
  name: string;
}

interface WorkersShiftRoleType {
  id: number;
  name: string;
}

export interface WorkingBranchesProps {
  workingBranches: WorkingBranch[];
  loading: boolean;
  userId: number;
  workersShiftRoleTypes: WorkersShiftRoleType[];
  mainBranch: MainBranch;
}

export interface BranchFindAll {
  branchFindAll: {
    id: number;
    name: string;
  }[];
  shiftRoleTypeFindAll: WorkersShiftRoleType[];
}

export interface UserChangeWorkingBranches {
  userChangeWorkingBranches: {
    id: number;
    workingBranches: {
      id: number;
      name: string;
    }[];
  };
}

export interface UserChangeWorkingBranchesVars {
  userId: number;
  branchIds: number[];
}

export interface UserChangeWorkersShiftRoleTypes {
  userChangeWorkersShiftRoleTypes: {
    id: number;
    workersShiftRoleTypes: WorkersShiftRoleType[];
  };
}

export interface UserChangeWorkersShiftRoleTypesVars {
  userId: number;
  shiftRoleTypeIds: number[];
}

export interface MainBranchProps {
  branches: WorkingBranch[];
  mainBranch: MainBranch;
  userId: number;
}

export interface UserChangeMainBranch {
  userChangeMainBranch: {
    id: number;
    mainBranch: MainBranch;
  };
}

export interface UserChangeMainBranchVars {
  userId: number;
  branchId: number;
}
