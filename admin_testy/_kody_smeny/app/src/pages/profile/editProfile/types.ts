export interface UserGetLogged {
  userGetLogged: {
    id: number;
    hasOwnCar: boolean;
    phoneNumber: string;
  };
}

export interface UserEditMyself {
  userEditMyself: {
    id: number;
    hasOwnCar: boolean;
    phoneNumber: string;
  };
}

export interface UserEditMyselfVariables {
  hasOwnCar: boolean;
  phoneNumber: string;
}

export interface OnSubmitValues {
  hasOwnCar: boolean;
  phoneNumber: string;
}

export interface EditProfileProps {
  hasOwnCar: boolean;
  phoneNumber: string;
  loading: boolean;
  onSubmit: (values: OnSubmitValues) => void;
}
