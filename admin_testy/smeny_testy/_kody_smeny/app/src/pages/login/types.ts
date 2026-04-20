export interface UserLogin {
  userLogin: {
    id: number;
    accessToken: string;
    name: string;
    surname: string;
    email: string;
    unconfirmedNextPreferredWeek: boolean;
    roles: {
      id: number;
      resources: {
        id: number;
        name: string;
      }[];
    }[];
    darkTheme: boolean;
  };
}

export interface LoginProps {
  onSubmit: (email: string, password: string) => void;
  badInputs: boolean;
  loading: boolean;
}
