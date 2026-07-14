import { CircularProgress } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import React from 'react';

const useStyles = makeStyles({
  center: {
    display: 'grid',
    alignItems: 'center',
    height: '100vh',
    justifyItems: 'center',
  },
});

const Loading = () => {
  const classes = useStyles();

  return (
    <div className={classes.center}>
      <CircularProgress color="primary" />
    </div>
  );
};

export default Loading;
