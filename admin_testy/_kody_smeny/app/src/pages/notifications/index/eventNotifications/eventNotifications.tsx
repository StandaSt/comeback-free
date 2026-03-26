import React from 'react';
import InfoIcon from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';

import MaterialTable from 'lib/materialTable';

import { EventNotification, EventNotificationsProps } from './types';

const Info = () => <InfoIcon color="primary" />;

const EventNotifications: React.FC<EventNotificationsProps> = props => {
  const router = useRouter();

  return (
    <MaterialTable
      isLoading={props.loading}
      data={props.eventNotifications}
      columns={[
        { title: 'Název', field: 'label' },
        { title: 'Popis události', field: 'description' },
        { title: 'Zpráva', field: 'message' },
      ]}
      actions={[
        {
          icon: Info,
          tooltip: 'Detail',
          onClick: (_, row: EventNotification) => {
            router.push({
              pathname: routes.notifications.eventNotification,
              query: { id: row.id },
            });
          },
        },
      ]}
    />
  );
};

export default EventNotifications;
