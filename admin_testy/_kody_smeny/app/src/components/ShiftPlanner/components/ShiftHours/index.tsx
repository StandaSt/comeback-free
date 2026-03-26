import { Grid, IconButton, TextField } from '@material-ui/core';
import AddIcon from '@material-ui/icons/AddCircle';
import RemoveIcon from '@material-ui/icons/RemoveCircleOutline';
import React from 'react';

import { ShiftHoursProps } from './types';

const ShiftHours = (props: ShiftHoursProps) => {
  const mappedHours = props.hours.map(hour => {
    const fromChangeHandler = (e: React.ChangeEvent<HTMLInputElement>) => {
      props.onHourChange(hour.id, e.target.value, hour.to);
    };
    const toChangeHandler = (e: React.ChangeEvent<HTMLInputElement>) => {
      props.onHourChange(hour.id, hour.from, e.target.value);
    };

    return (
      <Grid key={hour.id} item container spacing={2} alignItems="center">
        <Grid item>
          <IconButton
            size="medium"
            onClick={() => {
              props.onHourRemove(hour.id);
            }}
          >
            <RemoveIcon color="secondary" />
          </IconButton>
        </Grid>
        <Grid item>
          <TextField
            variant="outlined"
            label="od"
            type="number"
            value={hour.from}
            onChange={fromChangeHandler}
          />
        </Grid>
        <Grid item>
          <TextField
            variant="outlined"
            label="do"
            type="number"
            value={hour.to}
            onChange={toChangeHandler}
          />
        </Grid>
      </Grid>
    );
  });

  return (
    <Grid container spacing={2}>
      {mappedHours}
      <Grid item>
        <IconButton size="medium" onClick={props.onHourAdd}>
          <AddIcon color="primary" />
        </IconButton>
      </Grid>
    </Grid>
  );
};

export default ShiftHours;
