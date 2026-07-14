import { Divider, Grid } from '@material-ui/core';
import React from 'react';

import Activate from './activate';
import Deactivate from './deactivate';
import { ActionsIndexProps } from './types';

const ActionsIndex = (props: ActionsIndexProps) => (
  <>
    <Grid container spacing={2}>
      <Grid item />

      <Grid xs={12} item>
        <Activate active={props.active} branchId={props.branchId} />
      </Grid>
      <Grid xs={12} item>
        <Divider />
      </Grid>
      <Grid xs={12} item>
        <Deactivate active={props.active} branchId={props.branchId} />
      </Grid>
    </Grid>
  </>
);

export default ActionsIndex;
