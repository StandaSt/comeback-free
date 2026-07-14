import {
  Box,
  Grid,
  IconButton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Theme,
  Typography,
} from '@material-ui/core';
import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import resources from '@shift-planner/shared/config/api/resources';
import PersonIcon from '@material-ui/icons/Person';
import { useRouter } from 'next/router';

import hoursToIntervalsByGroup from 'components/hoursToIntervalsByGroup';

import useResources from '../resources/useResources';
import routes from '../../../../shared/config/app/routes';

import { MapHour, ShiftWeekSummaryProps, UserMapValue } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  circle: {
    width: theme.spacing(2),
    height: theme.spacing(2),
    minWidth: theme.spacing(2),
    minHeight: theme.spacing(2),
    borderRadius: '100%',
    marginRight: theme.spacing(2),
  },
  red: {
    backgroundColor: theme.palette.error.main,
  },
  green: {
    backgroundColor: theme.palette.success.main,
  },
}));

const ShiftWeekSummary = (props: ShiftWeekSummaryProps) => {
  const classes = useStyles();
  const router = useRouter();

  const canSeeUser = useResources([resources.users.see]);
  const mappedUsers = [];

  const sortedUsers = new Map(
    [...props.users.entries()].sort((v1, v2) => {
      const user1 = v1[1];
      const user2 = v2[1];

      const minIndex1 = Math.min(...user1.shiftRoleTypesIndexes);
      const minIndex2 = Math.min(...user2.shiftRoleTypesIndexes);

      if (minIndex1 > minIndex2) {
        return 1;
      }
      if (minIndex1 < minIndex2) {
        return -1;
      }

      return 0;
    }),
  );

  for (const userId of sortedUsers.keys()) {
    const value: UserMapValue = props.users.get(userId);

    const getIntervals = (dayName: string): JSX.Element[] => {
      const day: MapHour[] = value[dayName];

      return hoursToIntervalsByGroup(
        props.dayStart,
        day.map(d => ({
          startHour: d.startHour,
          group: d.shiftRoleType,
          halfHour: d.halfHour,
        })),
      ).map(interval => (
        <Typography key={`interval${interval.id}`}>
          {`${interval.from}:${interval.halfHour ? '30' : '00'} - ${
            interval.to
          }:00 (${interval.group})`}
        </Typography>
      ));
    };

    mappedUsers.push(
      <TableRow>
        <TableCell size="small" width={1}>
          {canSeeUser && (
            <IconButton
              onClick={() => {
                router.push({
                  pathname: routes.users.userDetail,
                  query: { userId },
                });
              }}
            >
              <PersonIcon color="primary" />
            </IconButton>
          )}
        </TableCell>
        <TableCell key={`user${userId}`}>
          <Box display="flex" alignItems="center">
            <div
              className={`${classes.circle} ${
                value.confirmed ? classes.green : classes.red
              }`}
            />
            {`${value.name} ${value.surname}`}
          </Box>
        </TableCell>
        <TableCell>{getIntervals('monday')}</TableCell>
        <TableCell>{getIntervals('tuesday')}</TableCell>
        <TableCell>{getIntervals('wednesday')}</TableCell>
        <TableCell>{getIntervals('thursday')}</TableCell>
        <TableCell>{getIntervals('friday')}</TableCell>
        <TableCell>{getIntervals('saturday')}</TableCell>
        <TableCell>{getIntervals('sunday')}</TableCell>
      </TableRow>,
    );
  }

  return (
    <>
      <Grid item xs={12}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Akce</TableCell>
              <TableCell>Pracovníci</TableCell>
              <TableCell>Pondělí</TableCell>
              <TableCell>Úterý</TableCell>
              <TableCell>Středa</TableCell>
              <TableCell>Čtvrtek</TableCell>
              <TableCell>Pátek</TableCell>
              <TableCell>Sobota</TableCell>
              <TableCell>Neděle</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>{mappedUsers}</TableBody>
        </Table>
      </Grid>
    </>
  );
};

export default ShiftWeekSummary;
