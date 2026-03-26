export interface ActionsIndexProps {
  branchId: number;
  active: boolean;
}

export interface EditProps {
  branchId: number;
}

export interface ActivateProps {
  branchId: number;
  active: boolean;
}

export interface BranchActivate {
  branchActivate: {
    id: number;
    active: boolean;
  };
}

export interface BranchActivateVars {
  id: number;
}
