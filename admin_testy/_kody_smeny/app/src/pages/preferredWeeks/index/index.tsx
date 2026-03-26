import { useQuery } from '@apollo/react-hooks';
import {
  Button,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Theme,
  Typography,
  useMediaQuery,
  useTheme,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import { gql } from 'apollo-boost';
import Link from 'next/link';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React from 'react';

import Paper from 'components/Paper';
import withPage from 'components/withPage';

import preferredWeeksBreadcrumbs from './breadcrumbs';
import preferredWeeksResources from './resources';
import { PreferredWeekGetRelevant } from './types';

const PREFERRED_WEEK_GET_RELEVANT = gql`
  {
    preferredWeekGetRelevant {
      id
      startDay
      lastEditTime
    }
    userGetLogged {
      workingBranchesCount
    }
    globalSettingsFindPreferredDeadline {
      id
      value
    }
  }
`;

const useStyles = makeStyles((theme: Theme) => ({
  deadline: {
    paddingTop: theme.spacing(1),
    textAlign: 'center',
  },
  fit: {
    display: 'flex',
    justifyContent: 'flex-end',
  },
  afterDeadline: {
    color: theme.palette.error.main,
  },
}));

const PreferredWeeksIndex = () => {
  const classes = useStyles({});
  const { data, loading } = useQuery<PreferredWeekGetRelevant>(
    PREFERRED_WEEK_GET_RELEVANT,
    {
      fetchPolicy: 'no-cache',
      pollInterval: 60000,
    },
  );
  const theme = useTheme();
  const small = useMediaQuery(theme.breakpoints.down('xs'));

  const formatDate = (date: Date): string => dateFormat(date, 'd. m.');

  const getEndDate = (startDate: Date): string => {
    const end = new Date(startDate);
    end.setDate(end.getDate() + 6);

    return formatDate(end);
  };

  const deadline = new Date(
    data?.globalSettingsFindPreferredDeadline.value || Date.now(),
  );

  const getRelativeDeadline = (startDate: Date) => {
    const relativeDeadline = new Date(startDate);
    relativeDeadline.setDate(
      relativeDeadline.getDate() + ((deadline.getDay() + 6) % 7) - 7,
    );
    relativeDeadline.setHours(deadline.getHours());

    const format = (f: string) => dateFormat(relativeDeadline, f);

    return [
      `${format('d. m. hh:00')}`,
      relativeDeadline.getTime() < new Date(Date.now()).getTime(),
    ];
  };

  return (
    <>
      <Paper title="Požadavky" loading={loading}>
        {data?.userGetLogged.workingBranchesCount === 0 ? (
          <Typography>
            Nemáte přiřazenou žádnou pobočku, kontaktujte správce systému pro
            přiřazení.
          </Typography>
        ) : (
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Od</TableCell>
                <TableCell>Do</TableCell>
                <TableCell />
              </TableRow>
            </TableHead>
            <TableBody>
              {data?.preferredWeekGetRelevant.map(week => {
                const relativeDeadline = getRelativeDeadline(week.startDay);
                const afterDeadline = relativeDeadline[1];

                return (
                  <TableRow key={week.id}>
                    <TableCell>{formatDate(week.startDay)}</TableCell>
                    <TableCell>{getEndDate(week.startDay)}</TableCell>
                    <TableCell align="right">
                      <div className={classes.fit}>
                        <div>
                          {!week.lastEditTime ? (
                            <Link
                              href={{
                                pathname: routes.preferredWeeks.week,
                                query: { id: week.id },
                              }}
                            >
                              <Button
                                color="primary"
                                variant="contained"
                                fullWidth={small}
                              >
                                Vyplnit
                              </Button>
                            </Link>
                          ) : (
                            <Link
                              href={{
                                pathname: routes.preferredWeeks.overview,
                                query: { id: week.id },
                              }}
                            >
                              <Button
                                color="primary"
                                variant="contained"
                                fullWidth={small}
                              >
                                Zobrazit
                              </Button>
                            </Link>
                          )}
                          <div className={classes.deadline}>
                            <div>Deadline:</div>
                            <div
                              className={
                                afterDeadline ? classes.afterDeadline : ''
                              }
                            >
                              {relativeDeadline}
                            </div>
                          </div>
                        </div>
                      </div>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </Paper>
    </>
  );
};

export default withPage(
  PreferredWeeksIndex,
  preferredWeeksBreadcrumbs,
  preferredWeeksResources,
);
