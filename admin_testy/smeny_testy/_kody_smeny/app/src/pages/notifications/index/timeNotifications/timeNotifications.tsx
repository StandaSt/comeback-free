import React from 'react';
import InfoIcon from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';
import { Box, Button } from '@material-ui/core';

import MaterialTable from 'lib/materialTable';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';
import OverlayLoading from 'components/OverlayLoading';

import { TimeNotificationsProps, Notification } from './types';

const Info = (): JSX.Element => <InfoIcon color="primary" />;

const TimeNotifications: React.FC<TimeNotificationsProps> = props => {
  const router = useRouter();

  return (
    <OverlayLoadingContainer>
      <OverlayLoading loading={props.loading} />
      <MaterialTable
        columns={[{ title: 'Název', field: 'name' }]}
        data={props.timeNotifications}
        actions={[
          {
            tooltip: 'Detail',
            icon: Info,
            onClick: (_, row: Notification) => {
              router.push({
                pathname: routes.notifications.timeNotification.index,
                query: { id: row.id },
              });
            },
          },
        ]}
      />
      <Box display="flex" justifyContent="flex-end" pt={2}>
        <Button color="primary" variant="contained" onClick={props.onAdd}>
          Přidat
        </Button>
      </Box>
    </OverlayLoadingContainer>
  );
};

export default TimeNotifications;
