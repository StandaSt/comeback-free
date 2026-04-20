export interface UserGetLogged {
  userGetLogged: {
    id: number;
    hasOwnCar: boolean | null;
    phoneNumber: string | null;
    workersShiftRoleTypes: {
      id: number;
      useCars: boolean;
    }[];
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
  car: boolean;
  phone: string;
}

export default interface MissingInfoDialogProps {
  car: boolean | null;
  phone: string | null;
  shiftRoleTypes: { useCars: boolean }[];
  loading: boolean;
  onSubmit: (values: OnSubmitValues) => void;
}
