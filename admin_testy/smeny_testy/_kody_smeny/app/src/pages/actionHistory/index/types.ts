export interface ActionHistory {
  id: number;
  name: string;
  date: Date;
  additionalData: string;
  user: {
    id: number;
    name: string;
    surname: string;
  };
}

export interface ActionHistoryPaginate {
  actionHistoryPaginate: { items: ActionHistory[]; totalCount: number };
}

export interface ActionHistoryPaginateVariables {
  limit: number;
  offset: number;
  filter: {
    name: string;
    userName: string;
    userSurname: string;
    date: string;
  };
  orderBy?: {
    fieldName: string;
    type: 'ASC' | 'DESC';
  };
}
