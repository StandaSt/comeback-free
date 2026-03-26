import { Button } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import React from 'react';
import { CSVLink } from 'react-csv';

import hoursToIntervalsByGroup from 'components/hoursToIntervalsByGroup';
import {
  CsvDownloadProps,
  MapHour,
  UserMapValue,
} from 'components/ShiftWeekSummary/types';

const useStyles = makeStyles({
  csvButton: {
    textDecoration: 'none',
  },
});

const CsvDownload = (props: CsvDownloadProps) => {
  const classes = useStyles();

  const data = [
    [
      'Pracovníci',
      'Pondělí',
      'Úterý',
      'Středa',
      'Čtvrtek',
      'Pátek',
      'Sobota',
      'Neděle',
    ],
  ];

  for (const userId of props.users.keys()) {
    const value: UserMapValue = props.users.get(userId);

    const getIntervals = (dayName: string) => {
      const day: MapHour[] = value[dayName];

      return hoursToIntervalsByGroup(
        props.dayStart,
        day.map(d => ({
          startHour: d.startHour,
          group: d.shiftRoleType,
          halfHour: d.halfHour,
        })),
      )
        .map(
          interval =>
            `${interval.from}:${interval.halfHour ? '30' : '00'} - ${
              interval.to
            }:00 (${interval.group})`,
        )
        .join('\n');
    };
    data.push([
      `${value.name} ${value.surname}`,
      getIntervals('monday'),
      getIntervals('tuesday'),
      getIntervals('wednesday'),
      getIntervals('thursday'),
      getIntervals('friday'),
      getIntervals('saturday'),
      getIntervals('sunday'),
    ]);
  }

  const CSVButton = (
    <Button variant="contained" color="primary" disabled={props.disabled}>
      Stáhnout csv
    </Button>
  );

  return (
    <>
      {props.disabled ? (
        CSVButton
      ) : (
        <CSVLink
          data={data}
          className={classes.csvButton}
          filename={`Naplánované_směny-${props.branchName}.csv`}
          separator=";"
        >
          {CSVButton}
        </CSVLink>
      )}
    </>
  );
};

export default CsvDownload;
