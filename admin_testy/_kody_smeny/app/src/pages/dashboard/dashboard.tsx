import { Grid, Typography } from '@material-ui/core';
import React from 'react';
import { Alert } from '@material-ui/lab';

import {
  checkNotificationPermission,
  checkPushNotifications,
} from '../../components/registerServiceWorkers';

const Dashboard: React.FC = () => (
  <Grid container spacing={2}>
    <Grid item xs={12} container justify="center">
      <Typography variant="h3">Směny Pizza Comeback</Typography>
    </Grid>
    {!checkPushNotifications() && (
      <Grid item xs={12}>
        <Alert severity="error">
          Váš prohlížeč nepodporuje notifikace. Nainstalujte si jiný, např.
          Chrome, Firefox, Edge atd.
        </Alert>
      </Grid>
    )}
    {checkPushNotifications() && !checkNotificationPermission() && (
      <Grid item xs={12}>
        <Alert severity="error">
          {'Nemáte povolené notifikace. Návody jak povolit notifikace pro '}
          <a
            href="https://support.google.com/chrome/answer/95472"
            target="_blank"
            rel="noreferrer"
          >
            Chrome
          </a>
          {' a '}
          <a
            href="https://support.mozilla.org/cs/kb/webova-oznameni-ve-firefoxu"
            target="_blank"
            rel="noreferrer"
          >
            Edge
          </a>
          .
        </Alert>
      </Grid>
    )}
  </Grid>
);

export default Dashboard;
