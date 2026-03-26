export interface AddReceiverMutation {
  timeNotificationReceiverGroupAddTimeNotificationReceiver: {
    id: number;
    receivers: {
      id: number;
    }[];
  };
}

export interface AddReceiverMutationVariables {
  timeNotificationReceiverGroup: number;
  timeNotificationReceiver: { resourceId?: number; roleId?: number };
}
