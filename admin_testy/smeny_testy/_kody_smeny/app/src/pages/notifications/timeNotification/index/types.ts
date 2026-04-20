export type Repeat = 'never' | 'daily' | 'weekly' | 'monthly' | 'yearly';

interface Receiver {
  id: string;
  resource: {
    id: number;
    label: string;
    category: {
      id: number;
      label: string;
    };
  };
  role: {
    id: number;
    name: string;
  };
}

interface ReceiverGroup {
  id: number;
  receivers: Receiver[];
}

export interface TimeNotificationQuery {
  timeNotificationFindById: {
    id: number;
    name: string;
    repeat: Repeat;
    date: Date;
    message: string;
    receiverGroups: ReceiverGroup[];
  };
}

export interface TimeNotificationQueryVariables {
  id: number;
}

interface TimeNotification {
  id: number;
  name: string;
  repeat: Repeat;
  date: Date;
  message: string;
  receiverGroups: {
    id: number;
    receivers: {
      id: string;
      resource: {
        id: number;
        label: string;
        category: {
          id: number;
          label: string;
        };
      };
      role: {
        id: number;
        name: string;
      };
    }[];
  }[];
}

export interface UpdateValues {
  name: string;
  repeat: Repeat;
  date?: Date;
  message: string;
}

export interface TimeNotificationProps {
  timeNotification: TimeNotification;
  loading: boolean;
  onUpdate: (values: UpdateValues) => void;
}

export interface UpdateTimeNotificationMutation {
  timeNotificationUpdate: {
    id: number;
    name: string;
    message: string;
    repeat: Repeat;
    date: Date;
  };
}

export interface UpdateTimeNotificationMutationVariables {
  id: number;
  name: string;
  message: string;
  repeat: Repeat;
  date?: Date;
}

export interface ReceiverGroupsProps {
  receiverGroups: ReceiverGroup[];
}

export interface ReceiverGroupProps {
  receivers: Receiver[];
  onReceiverAdd: () => void;
}
