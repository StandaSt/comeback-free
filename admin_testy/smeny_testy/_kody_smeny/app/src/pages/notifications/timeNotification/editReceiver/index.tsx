import React from 'react';
import { useMutation, useQuery } from '@apollo/react-hooks';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';
import routes from '@shift-planner/shared/config/app/routes';

import withPage from 'components/withPage';
import ReceiverEdit from 'components/ReceiverEdit';
import { SelectValues } from 'components/ReceiverEdit/types';

import timeNotificationBreadcrumbs from '../index/breadcrumbs';

import RECEIVER_QUERY from './queries/reciever';
import {
  EditReceiverMutation,
  EditReceiverMutationVariables,
  ReceiverQuery,
} from './types';
import EDIT_RECEIVER_MUTATION from './mutations/editReceiver';

const EditReceiverIndex: React.FC = () => {
  const router = useRouter();
  const { data: receiverData, loading: receiverLoading } = useQuery<
    ReceiverQuery
  >(RECEIVER_QUERY, {
    variables: { id: +router.query.receiverId },
    fetchPolicy: 'no-cache',
  });
  const [editReceiver, { loading: editReceiverLoading }] = useMutation<
    EditReceiverMutation,
    EditReceiverMutationVariables
  >(EDIT_RECEIVER_MUTATION);
  const { enqueueSnackbar } = useSnackbar();

  const selectHandler = (values: SelectValues) => {
    editReceiver({
      variables: {
        id: +router.query.receiverId,
        timeNotificationReceiver: values,
      },
    })
      .then(() => {
        enqueueSnackbar('Příjemce úspěšně upraven', { variant: 'success' });
        router.push({
          pathname: routes.notifications.timeNotification.index,
          query: {
            id: router.query.timeNotificationId,
          },
        });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se upravit příjemce', { variant: 'error' });
      });
  };

  const receiver = receiverData?.timeNotificationReceiverFindById;

  let defaultValues: SelectValues = {};
  if (receiverData) {
    defaultValues = {
      roleId: receiver.role?.id,
      resourceId: receiver.resource?.id,
    };
  }

  return (
    <ReceiverEdit
      title="Upravení příjemce"
      loading={receiverLoading || editReceiverLoading}
      defaultValues={defaultValues}
      onSelect={selectHandler}
    />
  );
};

export default withPage(EditReceiverIndex, [
  ...timeNotificationBreadcrumbs,
  { label: 'Upravení příjemce' },
]);
