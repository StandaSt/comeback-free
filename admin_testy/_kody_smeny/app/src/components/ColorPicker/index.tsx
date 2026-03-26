import React from 'react';
import { Box, Theme } from '@material-ui/core';
import ColorPickerPrefab from 'material-ui-color-picker';
import { makeStyles } from '@material-ui/core/styles';

import { ColorPickerProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  colorDot: {
    height: theme.spacing(4),
    width: theme.spacing(4),
    borderRadius: '100%',
    marginLeft: theme.spacing(2),
  },
}));

const ColorPicker: React.FC<ColorPickerProps> = props => {
  const classes = useStyles();

  return (
    <Box display="flex" alignItems="center">
      <ColorPickerPrefab
        variant={props.variant}
        label={props.label}
        defaultValue="#000000"
        value={props.value}
        onChange={props.onChange}
      />
      <div
        className={classes.colorDot}
        style={{ backgroundColor: props.value }}
      />
    </Box>
  );
};

export default ColorPicker;
