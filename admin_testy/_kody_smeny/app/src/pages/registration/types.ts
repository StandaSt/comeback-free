export interface FormTypes {
  email: string;
  name: string;
  surname: string;
  password1: string;
  password2: string;
}

export interface RegistrationProps {
  onSubmit: (values: FormTypes) => void;
  loading: boolean;
}

export interface UserRegisterMyself {
  userRegisterMyself: boolean;
}

export interface UserRegisterMyselfVars {
  email: string;
  name: string;
  surname: string;
  password: string;
}

export interface SuccessModalProps {
  open: boolean;
}
