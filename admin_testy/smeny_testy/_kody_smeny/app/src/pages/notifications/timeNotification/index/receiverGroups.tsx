import React from 'react';
import { Box, Typography } from '@material-ui/core';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';

import ReceiverGroup from './receiverGroup';
import { ReceiverGroupsProps } from './types';

const ReceiverGroups: React.FC<ReceiverGroupsProps> = props => {
  const router = useRouter();
  const mappedReceiverGroups = props.receiverGroups.map(receiverGroup => (
    <React.Fragment key={receiverGroup.id}>
      <ReceiverGroup
        receivers={receiverGroup.receivers}
        onReceiverAdd={() =>
          router.push({
            pathname: routes.notifications.timeNotification.addReceiver,
            query: {
              receiverGroupId: receiverGroup.id,
              timeNotificationId: router.query.id,
            },
          })
        }
      />
      <Typography>
        <Box pt={1} pb={1} fontWeight="bold">
          Nebo
        </Box>
      </Typography>
    </React.Fragment>
  ));

  return (
    <>
      {mappedReceiverGroups}
      <ReceiverGroup receivers={[]} onReceiverAdd={() => {}} />
    </>
  );
};

export default ReceiverGroups;
