import { Grid, TextField, Typography } from '@material-ui/core';
import React from 'react';

import { DayProps } from './types';

const Day = (props: DayProps) => {
  const { start } = props;
  const { end } = props;

  return (
    <>
      <Grid container spacing={2}>
        <Grid item xs={12}>
          <Typography variant="h5">{props.name}</Typography>
        </Grid>

        <Grid item xs={6} sm="auto">
          <TextField
            type="number"
            variant="outlined"
            label="Od"
            error={props.error}
            value={start || ''}
            onChange={e => props.onStartChange(e.target.value)}
          />
        </Grid>
        <Grid item xs={6} sm="auto">
          <TextField
            type="number"
            variant="outlined"
            label="Do"
            error={props.error}
            value={end || ''}
            onChange={e => props.onEndChange(e.target.value)}
          />
        </Grid>
      </Grid>
    </>
  );
};

export default Day;
