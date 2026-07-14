import {
  Box,
  Divider,
  Grid,
  IconButton,
  Theme,
  Typography,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import LockIcon from '@material-ui/icons/Lock';
import UnlockIcon from '@material-ui/icons/LockOpen';
import React, { useEffect, useState } from 'react';

import { HeadIndexProps } from 'components/ShiftPlanner/pages/table/head/types';
import WeekStepper from 'components/WeekStepper';

const useStyles = makeStyles((theme: Theme) => ({
  container: {
    position: 'sticky',
    top: 64,
    padding: theme.spacing(1),
    backgroundColor: theme.palette.common.white,
    zIndex: 100,
  },
}));

const HeadIndex = (props: HeadIndexProps) => {
  const classes = useStyles();
  const [pinned, setPinned] = useState<boolean>(true);

  useEffect(() => {
    const pinnedString = window.localStorage.getItem(
      'shiftPlanner.headerPinned',
    );
    if (pinnedString) {
      setPinned(Boolean(JSON.parse(pinnedString)));
    }
  }, []);

  useEffect(() => {
    window.localStorage.setItem('shiftPlanner.headerPinned', pinned.toString());
  }, [pinned]);

  return (
    <Box
      className={pinned ? classes.container : ''}
      style={{
        backgroundColor: props.color,
        transition: 'background-color 200ms linear',
      }}
    >
      <Grid container spacing={2}>
        <Grid item xs={12}>
          {props.headExtends}
        </Grid>

        {props.headExtends && (
          <Grid item xs={12}>
            <Divider />
          </Grid>
        )}

        <Grid item xs={12}>
          <WeekStepper
            onDayChange={props.onDayChange}
            defaultDay={props.defaultDay}
            color={props.color}
            left
          />
        </Grid>
        <Grid item xs={6}>
          <Typography variant="h5">{props.dayTitle}</Typography>
        </Grid>
        <Grid item xs={6} container justify="flex-end">
          <IconButton onClick={() => setPinned(prevState => !prevState)}>
            {!pinned ? <UnlockIcon /> : <LockIcon />}
          </IconButton>
        </Grid>
      </Grid>
    </Box>
  );
};

export default HeadIndex;
