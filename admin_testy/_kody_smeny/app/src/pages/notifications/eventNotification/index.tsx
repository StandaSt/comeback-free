import React from 'react';
import { useMutation, useQuery } from '@apollo/react-hooks';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';

import withPage from 'components/withPage';

import EVENT_NOTIFICATION_FIND_BY_ID from './queries/eventNotification';
import {
  EventNotificationEdit,
  EventNotificationEditVariables,
  EventNotificationFindById,
  EventNotificationFindByIdVariables,
  FormValues,
} from './types';
import EventNotification from './eventNotification';
import eventNotificationBreadcrumbs from './breadcrumbs';
import EVENT_NOTIFICATION_EDIT from './mutations/edit';

const EventNotificationIndex: React.FC = () => {
  const router = useRouter();
  const { enqueueSnackbar } = useSnackbar();
  const {
    data: eventNotificationData,
    loading: eventNotificationLoading,
  } = useQuery<EventNotificationFindById, EventNotificationFindByIdVariables>(
    EVENT_NOTIFICATION_FIND_BY_ID,
    { variables: { id: +router.query.id } },
  );
  const [
    editEventNotification,
    { loading: eventNotificationEditLoading },
  ] = useMutation<EventNotificationEdit, EventNotificationEditVariables>(
    EVENT_NOTIFICATION_EDIT,
  );

  const editHandler = (values: FormValues): void => {
    editEventNotification({
      variables: { id: +router.query.id, message: values.message },
    })
      .then(() => {
        enqueueSnackbar('Notifikace úspěšně upravena', { variant: 'success' });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se upravit notifikaci', {
          variant: 'error',
        });
      });
  };

  return (
    <EventNotification
      notification={eventNotificationData?.eventNotificationFindById}
      loading={eventNotificationLoading || eventNotificationEditLoading}
      onEdit={editHandler}
    />
  );
};

export default withPage(EventNotificationIndex, eventNotificationBreadcrumbs, [
  resources.notifications.eventSee,
]);
