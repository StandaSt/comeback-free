import { WithSnackbarProps } from 'notistack';

export interface UserRegister {
  userRegister: {
    id: number;
    email: string;
    generatedPassword: string;
  };
}

export interface UserRegisterVars {
  email: string;
  name: string;
  surname: string;
}

export type AddUserIndexProps = WithSnackbarProps;

export interface FormValues {
  email: string;
  name: string;
  surname: string;
}

export interface AddUserProps {
  onSubmit: (email: string, name: string, surname: string) => void;
  loading: boolean;
}

export interface UserLoginDataModalProps {
  open: boolean;
  close: () => void;
  email: string;
  password: string;
}
