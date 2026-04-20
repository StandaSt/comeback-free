import React from 'react';
import { useMutation, useQuery } from '@apollo/react-hooks';
import { useSnackbar } from 'notistack';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';

import {
  CreateTimeNotificationMutation,
  TimeNotificationsQuery,
} from './types';
import CREATE_TIME_NOTIFICATION_MUTATION from './mutations/createTimeNotification';
import TimeNotifications from './timeNotifications';
import TIME_NOTIFICATIONS_QUERY from './queries/timeNotifications';

const TimeNotificationsIndex: React.FC = () => {
  const {
    data: timeNotificationsData,
    loading: timeNotificationsLoading,
  } = useQuery<TimeNotificationsQuery>(TIME_NOTIFICATIONS_QUERY);
  const [
    createTimeNotification,
    { loading: createTimeNotificationLoading },
  ] = useMutation<CreateTimeNotificationMutation>(
    CREATE_TIME_NOTIFICATION_MUTATION,
  );
  const router = useRouter();
  const { enqueueSnackbar } = useSnackbar();

  const addHandler = (): void => {
    createTimeNotification()
      .then(res => {
        enqueueSnackbar('Časová notifikace úspěšně vytvořena', {
          variant: 'success',
        });
        router.push({
          pathname: routes.notifications.timeNotification.index,
          query: { id: res.data.timeNotificationCreate.id },
        });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se vytvořit časovou notifikaci', {
          variant: 'error',
        });
      });
  };

  return (
    <TimeNotifications
      onAdd={addHandler}
      loading={createTimeNotificationLoading || timeNotificationsLoading}
      timeNotifications={timeNotificationsData?.timeNotificationFindAll || []}
    />
  );
};

export default TimeNotificationsIndex;
