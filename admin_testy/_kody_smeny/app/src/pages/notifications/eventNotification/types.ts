export interface EventNotification {
  id: number;
  label: string;
  message: string;
  description: string;
  variables: {
    value: string;
    description: string;
  }[];
}

export interface EventNotificationFindById {
  eventNotificationFindById: EventNotification;
}

export interface EventNotificationFindByIdVariables {
  id: number;
}

export interface EventNotificationEdit {
  eventNotificationEdit: {
    id: number;
    message: string;
  };
}

export interface EventNotificationEditVariables {
  id: number;
  message: string;
}

export interface EventNotificationProps {
  notification?: EventNotification;
  loading: boolean;
  onEdit: (values: FormValues) => void;
}

export interface FormValues {
  message: string;
}
