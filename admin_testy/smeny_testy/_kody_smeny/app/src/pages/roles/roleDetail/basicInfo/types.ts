export interface FormTypes {
  name: string;
  maxUsers: string;
  sortIndex: number;
}

export interface RoleEdit {
  roleEdit: {
    id: number;
    name: string;
    maxUsers: number;
    sortIndex: number;
  };
}

export interface RoleEditVars {
  roleId: number;
  name: string;
  maxUsers: number;
  registrationDefault: boolean;
  sortIndex: number;
}
