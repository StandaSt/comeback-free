export interface UserPaginate {
  userPaginate: {
    items: {
      id: number;
      name: string;
      surname: string;
      email: string;
      active: boolean;
      approved: boolean;
      phoneNumber: number;
      notificationsActivated: boolean;
    }[];
    totalCount: number;
  };
}

export interface UserPaginateVars {
  limit: number;
  offset: number;
  filter: {
    email?: string;
    name?: string;
    surname?: string;
    active?: boolean[];
    approved?: boolean[];
  };
  email?: string;
  name?: string;
  surname?: string;
  orderBy?: { fieldName?: string; type?: string };
}
