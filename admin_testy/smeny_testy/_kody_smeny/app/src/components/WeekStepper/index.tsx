import {
  Box,
  Button,
  makeStyles,
  MobileStepper,
  Theme,
} from '@material-ui/core';
import LeftIcon from '@material-ui/icons/KeyboardArrowLeft';
import RightIcon from '@material-ui/icons/KeyboardArrowRight';
import React, { useState } from 'react';

import { WeekStepperProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  stepper: {
    background: theme.palette.background.paper,
  },
  center: {
    justifyContent: 'center',
  },
  left: {
    justifyContent: 'left',
  },
}));

const WeekStepper = (props: WeekStepperProps) => {
  const classes = useStyles();

  const [day, setDay] = useState(props.defaultDay || 0);

  const nextButton = (
    <Box pl={2}>
      <Button
        size="small"
        onClick={() => {
          props.onDayChange(day + 1);
          setDay(d => d + 1);
        }}
        disabled={day === 6 || props.buttonsDisabled}
        color="primary"
        variant="contained"
      >
        Dále
        <RightIcon />
      </Button>
    </Box>
  );
  const backButton = (
    <Box pr={2}>
      <Button
        size="small"
        onClick={() => {
          props.onDayChange(day - 1);
          setDay(d => d - 1);
        }}
        disabled={day === 0 || props.buttonsDisabled}
        color="primary"
        variant="contained"
      >
        <LeftIcon />
        Zpět
      </Button>
    </Box>
  );

  return (
    <MobileStepper
      variant="dots"
      className={`${classes.stepper} ${props.center ? classes.center : ''} ${
        props.left ? classes.left : ''
      }`}
      steps={7}
      position="static"
      activeStep={day}
      nextButton={nextButton}
      backButton={backButton}
      style={
        props.color
          ? {
              backgroundColor: props.color,
              transition: 'background-color 200ms linear',
            }
          : {}
      }
    />
  );
};

export default WeekStepper;
