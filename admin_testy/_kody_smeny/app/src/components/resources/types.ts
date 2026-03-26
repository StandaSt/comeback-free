export interface UserGetLogged {
  userGetLogged: {
    id: number;
    roles: {
      id: number;

      resources: {
        id: number;
        name: string;
      }[];
    }[];
  };
}
