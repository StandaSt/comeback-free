export interface User {
  id: number;
  name: string;
  surname: string;
  totalEvaluationScore: number | null;
}

export interface UserPaginate {
  userPaginate: {
    items: User[];
    totalCount: number;
  };
}
export interface UserPaginateVariables {
  filter?: {
    email?: string;
    name?: string;
    surname?: string;
    active?: boolean[];
    approved?: boolean[];
  };
  orderBy?: {
    fieldName: string;
    type: string;
  };
  limit: number;
  offset: number;
}
