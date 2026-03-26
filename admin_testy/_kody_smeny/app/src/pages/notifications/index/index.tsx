import React from 'react';
import resources from '@shift-planner/shared/config/api/resources';

import PaperWithTabs from 'components/PaperWithTabs';
import withPage from 'components/withPage';
import useResources from 'components/resources/useResources';

import EventNotificationsIndex from './eventNotifications';
import notificationsBreadcrumbs from './breadcrumbs';
import notificationsResources from './resources';
import TimeNotificationsIndex from './timeNotifications';

const NotificationsIndex: React.FC = () => {
  const canSeeEventNotifications = useResources([resources.notifications.see]);

  return (
    <PaperWithTabs
      title="Notifikace"
      tabs={[
        { label: 'Časové události', panel: <TimeNotificationsIndex /> },
        {
          label: 'Událostní notifikace',
          panel: <EventNotificationsIndex />,
          disabled: !canSeeEventNotifications,
        },
      ]}
    />
  );
};

export default withPage(
  NotificationsIndex,
  notificationsBreadcrumbs,
  notificationsResources,
);
