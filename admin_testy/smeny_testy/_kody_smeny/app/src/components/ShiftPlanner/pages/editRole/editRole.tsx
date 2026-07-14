import {
  Checkbox,
  FormControlLabel,
  Grid,
  MenuItem,
  Select,
  Typography,
} from '@material-ui/core';
import React from 'react';

import ShiftHours from 'components/ShiftPlanner/components/ShiftHours';

import { EditRoleProps } from './types';

const EditRole = (props: EditRoleProps) => {
  const mappedRoleTypes =
    props.roleTypes?.map(type => (
      <MenuItem key={`roleType-${type.id}`} value={type.id}>
        {type.name}
      </MenuItem>
    )) || [];

  return (
    <Grid container spacing={2}>
      <Grid item xs={12} />
      <Grid item>
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
        onHourAdd={props.onHourAdd}
        onHourChange={props.onHourChange}
        onHourRemove={props.onHourRemove}
      />
    </Grid>
  );
};

export default EditRole;
