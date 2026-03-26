interface User {
  id: number;
  email: string;
  name: string;
  surname: string;
  active: boolean;
}

export interface RoleFindById {
  roleFindById: {
    id: number;
    users: User[];
  };
}

export interface RoleFindByIdVars {
  id: number;
}

export interface UsersProps {
  users: User[];
}
