export interface Notification {
  id: number;
  name: string;
}

export interface TimeNotificationsProps {
  loading: boolean;
  onAdd: () => void;
  timeNotifications: Notification[];
}

export interface CreateTimeNotificationMutation {
  timeNotificationCreate: {
    id: number;
  };
}

export interface TimeNotificationsQuery {
  timeNotificationFindAll: {
    id: number;
    name: string;
  }[];
}
