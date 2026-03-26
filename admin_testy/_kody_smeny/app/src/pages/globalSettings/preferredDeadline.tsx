import {
  FormControl,
  InputLabel,
  makeStyles,
  MenuItem,
  Select,
  TextField,
  Theme,
} from '@material-ui/core';
import React from 'react';

import { PreferredDeadlineProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  select: {
    width: theme.spacing(10),
  },
  hours: {
    width: theme.spacing(10),
    marginLeft: theme.spacing(2),
  },
}));

const PreferredDeadline = (props: PreferredDeadlineProps) => {
  const classes = useStyles();

  const days = [
    'pondělí',
    'úterý',
    'středa',
    'čtvrtek',
    'pátek',
    'sobota',
    'neděle',
  ];

  const mappedDays = days.map((day, index) => (
    <MenuItem value={index} key={day}>
      {day}
    </MenuItem>
  ));

  const dayNumber = (7 + props.deadline.getDay() - 1) % 7;

  const day = days[dayNumber];
  const hour = props.deadline.getHours();

  const hourChangeHandler = (e: React.ChangeEvent<HTMLInputElement>) => {
    const h = +e.target.value;
    const newDeadline = new Date(props.deadline);

    if (h >= 0 && h < 24) {
      newDeadline.setHours(h);
      props.onDeadlineChange(newDeadline);
    }
  };

  const dayChangeHandler = (d: number) => {
    const newDeadline = new Date(props.deadline);
    newDeadline.setDate(newDeadline.getDate() + (1 + d - newDeadline.getDay()));
    props.onDeadlineChange(newDeadline);
  };

  return (
    <>
      {props.editing ? (
        <>
          <FormControl className={classes.select} defaultValue={dayNumber}>
            <InputLabel id="daySelectInput">Den</InputLabel>
            <Select
              labelId="daySelectInput"
              value={dayNumber}
              onChange={e => dayChangeHandler(+e.target.value)}
            >
              {mappedDays}
            </Select>
          </FormControl>
          <TextField
            className={classes.hours}
            label="Hodina"
            type="number"
            value={hour}
            onChange={hourChangeHandler}
          />
        </>
      ) : (
        <div>{`${day} ${hour}:00`}</div>
      )}
    </>
  );
};

export default PreferredDeadline;
