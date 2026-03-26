import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import { Theme } from '@material-ui/core';

import { ColorDotProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  colorDot: {
    height: theme.spacing(4),
    width: theme.spacing(4),
    minWidth: theme.spacing(4),
    minHeight: theme.spacing(4),
    borderRadius: '100%',
    marginLeft: theme.spacing(2),
  },
}));

const ColorDot: React.FC<ColorDotProps> = props => {
  const classes = useStyles();

  return (
    <div
      className={classes.colorDot}
      style={{ backgroundColor: props.color }}
    />
  );
};

export default ColorDot;
