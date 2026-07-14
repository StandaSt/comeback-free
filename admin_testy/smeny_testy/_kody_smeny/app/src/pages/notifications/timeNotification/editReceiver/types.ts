export interface ReceiverQuery {
  timeNotificationReceiverFindById: {
    id: number;
    role?: {
      id: number;
    };
    resource?: {
      id: number;
    };
  };
}

export interface ReceiverQueryVariables {
  id: number;
}

export interface EditReceiverMutation {
  timeNotificationReceiverEdit: {
    id: number;
  };
}

export interface EditReceiverMutationVariables {
  id: number;
  timeNotificationReceiver: {
    resourceId?: number;
    roleId?: number;
  };
}
