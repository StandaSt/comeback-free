export interface FormValues {
  oldPassword: string;
  newPassword1: string;
  newPassword2: string;
}

export interface UserChangeMyPassword {
  userResetMyPassword: {
    id: number;
  };
}

export interface UserChangeMyPasswordVars {
  old: string;
  new: string;
}
