export interface BranchFindAll {
  branchFindAll: {
    id: number;
    name: string;
  }[];
  shiftRoleTypeFindAll: {
    id: number;
    name: string;
  }[];
}

export interface UserChangePlanableBranches {
  userChangePlanableBranches: {
    id: number;
    planableBranches: {
      id: number;
      name: string;
    };
  };
}

export interface UserChangePlanableBranchesVars {
  userId: number;
  branchesIds: number[];
}

export interface UserChangePlanableShiftRoleTypes {
  userChangePlanableShiftRoleTypes: {
    planableShiftRoleTypes: {
      id: number;
      name: string;
    }[];
  };
}

export interface UserChangePlanableShiftRoleTypesVariables {
  userId: number;
  shiftRoleTypeIds: number[];
}
