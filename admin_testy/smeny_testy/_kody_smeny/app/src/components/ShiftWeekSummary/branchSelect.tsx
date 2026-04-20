import {
  FormControl,
  InputLabel,
  MenuItem,
  Select,
  Theme,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import React from 'react';

import { BranchSelectProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  select: {
    minWidth: theme.spacing(30),
  },
}));

const BranchSelect = (props: BranchSelectProps) => {
  const classes = useStyles();

  const mappedItems = props.branches.map(branch => (
    <MenuItem key={branch.id} value={branch.id}>
      {branch.name}
    </MenuItem>
  ));

  return (
    <>
      <FormControl variant="outlined" className={classes.select}>
        <InputLabel id="label">Pobočka</InputLabel>
        <Select
          label="Pobočka"
          value={props.selected}
          onChange={e => props.onChange(+e.target.value)}
        >
          {mappedItems}
        </Select>
      </FormControl>
    </>
  );
};

export default BranchSelect;
