import React from 'react';
import resources from '@shift-planner/shared/config/api/resources';
import { Box } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import dayjs from 'dayjs';
import PersonIcon from '@material-ui/icons/Person';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';
import StarRateIcon from '@material-ui/icons/StarRate';

import MaterialTable from 'lib/materialTable';
import hoursToIntervals from 'components/hoursToIntervals';

import useResources from '../resources/useResources';
import evaluationResources from '../../pages/evaluation/index/resources';

import {
  EnteredPreferredWeeksUIProps,
  PreferredWeek,
  PreferredWeekWithPercents,
} from './types';

const useStyles = makeStyles({
  noWrap: {
    whiteSpace: 'nowrap',
  },
});

const Person = (): JSX.Element => <PersonIcon color="primary" />;
const StarRate = (): JSX.Element => <StarRateIcon color="primary" />;

const EnteredPreferredWeeksUI: React.FC<EnteredPreferredWeeksUIProps> = props => {
  const classes = useStyles();
  const canSeeEvaluationHistory = useResources([resources.evaluation.history]);
  const router = useRouter();

  const renderPreferredHours = (
    row: PreferredWeek,
    day: string,
  ): JSX.Element[] => {
    const preferredDay = row.preferredDays.find(d => d.day === day);

    let { preferredHours } = preferredDay;
    preferredHours = preferredHours.filter(h => h.visible);

    return hoursToIntervals(
      8,
      preferredHours.map(h => h.startHour),
    ).map(i => <b className={classes.noWrap}>{`${i.from}:00 - ${i.to}:00`}</b>);
  };

  const renderNotAssignedHours = (
    row: PreferredWeek,
    day: string,
  ): JSX.Element[] => {
    const preferredDay = row.preferredDays.find(d => d.day === day);

    let { preferredHours } = preferredDay;
    preferredHours = preferredHours.filter(h => h.notAssigned);

    return hoursToIntervals(
      8,
      preferredHours.map(h => h.startHour),
    ).map(i => (
      <span className={classes.noWrap}>{`${i.from}:00 - ${i.to}:00`}</span>
    ));
  };

  const renderDay = (row: PreferredWeek, day: string): JSX.Element => (
    <Box display="flex" flexDirection="column">
      {renderPreferredHours(row, day)}
      {renderNotAssignedHours(row, day)}
    </Box>
  );

  const branchLookup = {};
  props.branches.forEach(b => {
    branchLookup[b.id] = b.name;
  });

  const shiftRoleTypesLookup = {};
  props.shiftRoleTypes.forEach(t => {
    shiftRoleTypesLookup[t.name] = t.name;
  });

  const canSeeUser = useResources([resources.users.see]);
  const canSeeEvaluation = useResources(evaluationResources);

  return (
    <>
      <MaterialTable
        isLoading={props.loading}
        columns={[
          { title: 'Jméno', field: 'user.name' },
          { title: 'Příjmení', field: 'user.surname' },
          {
            title: 'Pozice',
            field: 'user.shiftRoleTypeNames',
            render: (row: PreferredWeek) =>
              row.user.shiftRoleTypeNames.join(', '),
            lookup: shiftRoleTypesLookup,
            customFilterAndSearch: (filter: any, row: PreferredWeek) => {
              return row.user.shiftRoleTypeNames.some(t =>
                filter.some(f => f === t),
              );
            },
          },
          {
            title: 'Pobočky',
            field: 'user.workingBranchNames',
            render: (row: PreferredWeek) =>
              row.user.workingBranches.map(b => b.name).join(', '),
            customFilterAndSearch: (filter: string[], row: PreferredWeek) => {
              if (filter.length === 0) return true;

              return row.user.workingBranches.some(b =>
                filter.some(f => f === b.id.toString()),
              );
            },
            lookup: branchLookup,
            sorting: false,
          },
          {
            title: 'Hodnocení',
            field: 'user.totalEvaluationScore',
            hidden: !canSeeEvaluationHistory,
            filtering: false,
          },
          {
            title: 'Využito',
            field: 'percents',
            defaultSort: 'asc',
            render: (row: PreferredWeekWithPercents) =>
              `${row.percents}% (${row.totalUsedPreferredHours}/${row.totalPreferredHours})`,
            filtering: false,
          },
          {
            title: 'Naposledy upraveno',
            field: 'user.lastEditTime',
            customSort: (data1: PreferredWeek, data2: PreferredWeek) => {
              if (
                data1.lastEditTime === data2.lastEditTime &&
                data1.lastEditTime === null
              ) {
                return 0;
              }
              if (data1.lastEditTime === null) return -1;
              if (data2.lastEditTime === null) return 1;

              const date1 = dayjs(data1.lastEditTime).get('date');
              const date2 = dayjs(data2.lastEditTime).get('date');
              if (date1 < date2) return -1;
              if (date1 > date2) return 1;

              return 0;
            },
            render: (row: PreferredWeek) => {
              if (row.lastEditTime === null) return '-';

              return dayjs(row.lastEditTime).format('DD. MM.');
            },
            filtering: false,
          },
          {
            title: 'Pondělí',
            render: (row: PreferredWeek) => renderDay(row, 'monday'),
          },
          {
            title: 'Úterý',
            render: (row: PreferredWeek) => renderDay(row, 'tuesday'),
          },
          {
            title: 'Středa',
            render: (row: PreferredWeek) => renderDay(row, 'wednesday'),
          },
          {
            title: 'Čtvrtek',
            render: (row: PreferredWeek) => renderDay(row, 'thursday'),
          },
          {
            title: 'Pátek',
            render: (row: PreferredWeek) => renderDay(row, 'friday'),
          },
          {
            title: 'Sobota',
            render: (row: PreferredWeek) => renderDay(row, 'saturday'),
          },
          {
            title: 'Neděle',
            render: (row: PreferredWeek) => renderDay(row, 'sunday'),
          },
        ]}
        actions={[
          {
            tooltip: 'Uživatel',
            icon: Person,
            onClick: (_, row: PreferredWeek) => {
              router.push({
                pathname: routes.users.userDetail,
                query: { userId: row.user.id },
              });
            },
            hidden: !canSeeUser,
          },
          {
            tooltip: 'Hodnocení',
            icon: StarRate,
            onClick: (_, row: PreferredWeek) => {
              router.push({
                pathname: routes.evaluation.detail,
                query: { id: row.user.id },
              });
            },
            hidden: !canSeeEvaluation,
          },
        ]}
        data={props.preferredWeeks}
        options={{ filtering: true }}
      />
    </>
  );
};

export default EnteredPreferredWeeksUI;
