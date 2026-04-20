import { Theme, Typography } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import React from 'react';

import hoursToIntervals from 'components/hoursToIntervals';

import { CurrentWorkersProps, ShiftHour } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  rightCell: {
    paddingLeft: theme.spacing(2),
    textAlign: 'right',
  },
}));

const CurrentWorkers = (props: CurrentWorkersProps) => {
  const classes = useStyles();

  const workers = new Map<number, ShiftHour[]>();
  // eslint-disable-next-line no-unused-expressions
  props.shiftRole?.shiftHours.forEach(shiftHour => {
    if (shiftHour.employee) {
      const key = shiftHour.employee.id;
      const value = shiftHour;

      const oldValue = workers.get(key) || [];
      workers.set(key, [...oldValue, value]);
    }
  });

  const mappedWorkers = [];

  for (const key of workers.keys()) {
    const value = workers.get(key);
    const intervals = hoursToIntervals(
      props.dayStart,
      value.map(h => h.startHour),
    );

    intervals.forEach(interval => {
      const { employee } = value[0];
      mappedWorkers.push(
        <tr>
          <td>
            <Typography>{`${employee.name} ${employee.surname}`}</Typography>
          </td>
          <td className={classes.rightCell}>
            <Typography>{`${interval.from}:00-${interval.to}:00`}</Typography>
          </td>
        </tr>,
      );
    });
  }

  return (
    <>
      <table>{mappedWorkers}</table>
    </>
  );
};

export default CurrentWorkers;
