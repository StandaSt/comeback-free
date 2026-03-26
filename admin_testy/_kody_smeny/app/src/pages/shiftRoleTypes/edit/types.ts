export interface ShiftRoleTypeFindById {
  shiftRoleTypeFindById: {
    id: number;
    name: string;
    registrationDefault: boolean;
    sortIndex: number;
    color: string;
    useCars: boolean;
  };
}

export interface ShiftRoleTypeFindByIdVars {
  id: number;
}

export interface ShiftRoleTypeEdit {
  shiftRoleTypeEdit: {
    id: number;
    name: string;
    registrationDefault: boolean;
    sortIndex: number;
    color: string;
  };
}

export interface ShiftRoleTypeEditVars {
  id: number;
  name: string;
  registrationDefault: boolean;
  sortIndex: number;
  color: string;
  useCars: boolean;
}
