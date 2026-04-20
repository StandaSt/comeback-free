import React from 'react';
import { useMutation, useQuery } from '@apollo/react-hooks';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';

import withPage from 'components/withPage';

import TimeNotification from './timeNotification';
import timeNotificationBreadcrumbs from './breadcrumbs';
import TIME_NOTIFICATION_QUERY from './queries/timeNotification';
import {
  TimeNotificationQuery,
  TimeNotificationQueryVariables,
  UpdateTimeNotificationMutation,
  UpdateTimeNotificationMutationVariables,
  UpdateValues,
} from './types';
import UPDATE_TIME_NOTIFICATION_MUTATION from './mutations/updateTimeNotification';

const TimeNotificationIndex: React.FC = () => {
  const router = useRouter();
  const {
    data: timeNotificationData,
    loading: timeNotificationLoading,
  } = useQuery<TimeNotificationQuery, TimeNotificationQueryVariables>(
    TIME_NOTIFICATION_QUERY,
    { variables: { id: +router.query.id }, fetchPolicy: 'no-cache' },
  );
  const [
    updateTimeNotification,
    { loading: updateTimeNotificationLoading },
  ] = useMutation<
    UpdateTimeNotificationMutation,
    UpdateTimeNotificationMutationVariables
  >(UPDATE_TIME_NOTIFICATION_MUTATION);
  const { enqueueSnackbar } = useSnackbar();

  const updateHandler = (values: UpdateValues): void => {
    updateTimeNotification({ variables: { ...values, id: +router.query.id } })
      .then(() => {
        enqueueSnackbar('Časová notifikace úspěšně uložena', {
          variant: 'success',
        });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se uložit časovou notifikaci', {
          variant: 'error',
        });
      });
  };

  return (
    <TimeNotification
      loading={timeNotificationLoading || updateTimeNotificationLoading}
      timeNotification={timeNotificationData?.timeNotificationFindById}
      onUpdate={updateHandler}
    />
  );
};

export default withPage(TimeNotificationIndex, timeNotificationBreadcrumbs);
