import React from 'react';
import { InputLabel, MenuItem, Select } from '@material-ui/core';

import { BranchSelectProps } from 'pages/weekPlanning/shared/types';

const BranchSelect: React.FC<BranchSelectProps> = props => (
  <>
    <InputLabel id="branch-select-label">Pobočka</InputLabel>
    <Select
      labelId="branch-select-label"
      value={props.selectedBranch}
      onChange={e => {
        props.onBranchChange(+e.target.value);
      }}
    >
      <MenuItem value={-1}>---</MenuItem>
      {props.branches.map(b => (
        <MenuItem key={b.id} value={b.id}>
          {b.name}
        </MenuItem>
      ))}
    </Select>
  </>
);

export default BranchSelect;
