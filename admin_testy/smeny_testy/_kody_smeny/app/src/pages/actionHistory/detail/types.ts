export interface ActionHistoryFindById {
  actionHistoryFindById: {
    id: number;
    name: string;
    date: string;
    additionalData: string;
    user: {
      id: number;
      name: string;
      surname: string;
    };
  };
}

export interface ActionHistoryFindByIdVariables {
  id: number;
}
