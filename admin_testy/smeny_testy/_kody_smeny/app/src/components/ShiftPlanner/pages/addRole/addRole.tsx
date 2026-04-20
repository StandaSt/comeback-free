import {
  Box,
  Checkbox,
  FormControlLabel,
  Grid,
  InputLabel,
  MenuItem,
  Select,
  Typography,
} from '@material-ui/core';
import React from 'react';

import ShiftHours from 'components/ShiftPlanner/components/ShiftHours';

import { AddRoleProps } from './types';

const AddRole = (props: AddRoleProps) => {
  const mappedRoleTypes =
    props.roleTypes?.map(type => (
      <MenuItem key={`roleType-${type.id}`} value={type.id}>
        {type.name}
      </MenuItem>
    )) || [];

  return (
    <Grid container spacing={2}>
      <Grid item xs={12} />
      <Grid item xs={12}>
        <InputLabel id="shift-role-type-select-label">Typ slotu</InputLabel>
        <Select
          labelId="shift-role-type-select-label"
          variant="outlined"
          value={props.currentRoleType}
          onChange={e => {
            props.onRoleTypeChange(+e.target.value);
          }}
        >
          <MenuItem value={-1}>---</MenuItem>
          {mappedRoleTypes}
        </Select>
      </Grid>
      <Grid item xs={12}>
        <FormControlLabel
          control={
            // eslint-disable-next-line react/jsx-wrap-multilines
            <Checkbox
              checked={props.halfHour}
              onChange={e => props.onHalfHourChange(e.target.checked)}
            />
          }
          label="+30"
        />
      </Grid>
      <Grid item xs={12}>
        <Typography variant="h6">Služba</Typography>
      </Grid>
      <ShiftHours
        hours={props.hours}
        onHourChange={props.onHourChange}
        onHourRemove={props.onHourRemove}
        onHourAdd={props.onHourAdd}
      />
    </Grid>
  );
};

export default AddRole;
