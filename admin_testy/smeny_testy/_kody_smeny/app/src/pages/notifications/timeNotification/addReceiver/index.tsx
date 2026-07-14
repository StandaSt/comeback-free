import React from 'react';
import { useMutation } from '@apollo/react-hooks';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';
import routes from '@shift-planner/shared/config/app/routes';

import withPage from 'components/withPage';
import ReceiverEdit from 'components/ReceiverEdit';
import { SelectValues } from 'components/ReceiverEdit/types';

import timeNotificationBreadcrumbs from '../index/breadcrumbs';

import ADD_RECEIVER_MUTATION from './mutations/addReceiver';
import { AddReceiverMutation, AddReceiverMutationVariables } from './types';

const AddReceiverIndex: React.FC = () => {
  const [addReceiver, { loading: addReceiverLoading }] = useMutation<
    AddReceiverMutation,
    AddReceiverMutationVariables
  >(ADD_RECEIVER_MUTATION);
  const router = useRouter();
  const { enqueueSnackbar } = useSnackbar();

  const selectHandler = (values: SelectValues): void => {
    addReceiver({
      variables: {
        timeNotificationReceiverGroup: +router.query.receiverGroupId,
        timeNotificationReceiver: {
          ...values,
        },
      },
    })
      .then(() => {
        enqueueSnackbar('Příjemce úspěšně přidán', { variant: 'success' });
        router.push({
          pathname: routes.notifications.timeNotification.index,
          query: { id: router.query.timeNotificationId },
        });
      })
      .catch(() => {
        enqueueSnackbar('Nepodařilo se přidat příjemce', { variant: 'error' });
      });
  };

  return (
    <ReceiverEdit
      title="Přidání příjemce"
      loading={addReceiverLoading}
      onSelect={selectHandler}
    />
  );
};

export default withPage(AddReceiverIndex, [
  ...timeNotificationBreadcrumbs,
  { label: 'Přidání příjemce' },
]);
