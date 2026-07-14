interface User {
  id: number;
  name: string;
  surname: string;
  email: string;
  createTime: Date;
  lastLoginTime: Date;
  mainBranchName: string;
  workingBranchNames: string[];
  shiftRoleTypeNames: string[];
  hasOwnCar: boolean;
  phoneNumber: string;
}

export interface UserGetLogged {
  userGetLogged: User;
}

export interface PreferencesProps {
  loading: boolean;
}
