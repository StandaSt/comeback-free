import React from 'react';
import { useQuery } from '@apollo/react-hooks';

import EventNotifications from './eventNotifications';
import EVENT_NOTIFICATIONS_FIND_ALL from './queries/findAll';
import { EventNotificationFindAll } from './types';

const EventNotificationsIndex: React.FC = () => {
  const {
    data: eventNotificationsData,
    loading: eventNotificationsLoading,
  } = useQuery<EventNotificationFindAll>(EVENT_NOTIFICATIONS_FIND_ALL);

  return (
    <EventNotifications
      loading={eventNotificationsLoading}
      eventNotifications={
        eventNotificationsData?.eventNotificationFindAll || []
      }
    />
  );
};

export default EventNotificationsIndex;
