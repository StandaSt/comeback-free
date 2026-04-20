export interface EventNotification {
  id: number;
  label: string;
  message: string;
}

export interface EventNotificationFindAll {
  eventNotificationFindAll: EventNotification[];
}

export interface EventNotificationsProps {
  eventNotifications: EventNotification[];
  loading: boolean;
}
