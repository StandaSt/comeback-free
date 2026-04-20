import { useMutation, useQuery } from '@apollo/react-hooks';
import {
  Badge,
  Box,
  Checkbox,
  FormControlLabel,
  Grid,
  makeStyles,
  TextField,
  Theme,
  Typography,
  useTheme,
} from '@material-ui/core';
import capitalize from '@material-ui/core/utils/capitalize';
import Add from '@material-ui/icons/Add';
import CarIcon from '@material-ui/icons/DirectionsCar';
import StarRate from '@material-ui/icons/StarRate';
import Person from '@material-ui/icons/Person';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import apiErrors from '@shift-planner/shared/config/api/errors';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React, { useState } from 'react';
import resources from '@shift-planner/shared/config/api/resources';

import MaterialTable from 'lib/materialTable';
import Actions from 'components/Actions';
import dayList, { translatedDayList } from 'components/dayList';
import hoursToIntervals from 'components/hoursToIntervals';
import hoursToIntervalsByGroup from 'components/hoursToIntervalsByGroup';
import LoadingButton from 'components/LoadingButton';
import shiftRoleFragment from 'components/ShiftPlanner/fragments/shiftRoleFragment';
import shiftPlannerRoutes from 'components/ShiftPlanner/routes';
import useResources from 'components/resources/useResources';
import evaluationResources from 'pages/evaluation/index/resources';

import CurrentWorkers from './currentWorkers';
import ForceDialog from './forceDialog';
import {
  AssignWorkerProps,
  RelevantUser,
  RelevantUserFindAllForShiftRole,
  RelevantUserFindAllForShiftRoleVars,
  ShiftRoleAssignWorker,
  ShiftRoleAssignWorkerVars,
  ShiftRoleUnassignWorker,
  ShiftRoleUnassignWorkerVars,
} from './types';

const useStyles = makeStyles((theme: Theme) => ({
  afterDeadline: {
    color: theme.palette.error.main,
  },
}));

const RELEVANT_USER_FIND_ALL_FOR_SHIFT_ROLE = gql`
  query(
    $shiftRoleId: Int!
    $startHour: Int!
    $endHour: Int!
    $withoutPreferredHours: Boolean
  ) {
    relevantUserFindAllForShiftRole(
      shiftRoleId: $shiftRoleId
      startHour: $startHour
      endHour: $endHour
      withoutPreferredHours: $withoutPreferredHours
    ) {
      id
      name
      surname
      preferredHours {
        id
        startHour
        notAssigned
        assignedToBranch {
          id
          name
        }
      }
      lastPreferredTime
      mainBranch
      afterDeadline
      perfectMatch
      totalWeekHours
      totalPreferredHours
      hasOwnCar
      user {
        id
        workingBranches {
          id
          name
        }
        totalEvaluationScore
      }
    }
    shiftRoleFindById(id: $shiftRoleId) {
      id
      halfHour
      firstHour
      shiftHours {
        id
        startHour
        employee {
          id
          name
          surname
        }
      }
      type {
        id
        name
        useCars
      }
      shiftDay {
        id
        day
        shiftWeek {
          id
          startDay
          branch {
            id
            name
          }
        }
      }
    }
    globalSettingsFindDayStart {
      id
      value
    }
  }
`;

const SHIFT_ROLE_ASSIGN_WORKER = gql`
  ${shiftRoleFragment}
  mutation($shiftRoleId: Int!, $userId: Int!, $from: Int!, $to: Int!) {
    shiftRoleAssignWorker(
      shiftRoleId: $shiftRoleId
      userId: $userId
      from: $from
      to: $to
    ) {
      ...ShiftRole
    }
  }
`;

const SHIFT_ROLE_UNASSIGN_WORKER = gql`
  ${shiftRoleFragment}
  mutation($shiftRoleId: Int!, $from: Int!, $to: Int!) {
    shiftRoleUnassignWorker(shiftRoleId: $shiftRoleId, from: $from, to: $to) {
      ...ShiftRole
    }
  }
`;

const AddIcon = (): JSX.Element => <Add color="primary" />;
const PersonIcon = (): JSX.Element => <Person color="primary" />;
const StarRateIcon = (): JSX.Element => <StarRate color="primary" />;

const AssignWorker: React.FC<AssignWorkerProps> = props => {
  const classes = useStyles();
  const [withoutPreferredHours, setWithoutPreferredHours] = useState(false);
  const [shiftRoleAssignWorker, { loading: mutationLoading }] = useMutation<
    ShiftRoleAssignWorker,
    ShiftRoleAssignWorkerVars
  >(SHIFT_ROLE_ASSIGN_WORKER);
  const [shiftRoleUnassignWorker, { loading: unassginLoading }] = useMutation<
    ShiftRoleUnassignWorker,
    ShiftRoleUnassignWorkerVars
  >(SHIFT_ROLE_UNASSIGN_WORKER);
  const [state, setState] = useState({
    start: '',
    end: '',
    setDefaults: false,
    setEnd: false,
    forceModal: null,
  });
  const { data, loading, error } = useQuery<
    RelevantUserFindAllForShiftRole,
    RelevantUserFindAllForShiftRoleVars
  >(RELEVANT_USER_FIND_ALL_FOR_SHIFT_ROLE, {
    fetchPolicy: 'no-cache',
    variables: {
      startHour: +state.start,
      endHour: +state.end,
      shiftRoleId: +props.query.shiftRoleId,
      withoutPreferredHours,
    },
  });
  const { enqueueSnackbar } = useSnackbar();
  const theme = useTheme();
  const canSeeEvaluation = useResources(evaluationResources);
  const canSeeUser = useResources([resources.users.see]);

  if (data && !state.setEnd) {
    const hours = data?.shiftRoleFindById.shiftHours;
    const intervals = hoursToIntervals(
      +data?.globalSettingsFindDayStart.value,
      hours.map(h => h.startHour),
    );
    setState(prevState => ({
      ...prevState,
      end: intervals[0].to.toString(),
      setEnd: true,
    }));
  }

  if (!state.setDefaults && props.query.startHour) {
    const startHour = +props.query.startHour;
    let endHour = startHour + 1;
    if (endHour === 24) endHour = 0;

    setState(s => ({
      ...s,
      setDefaults: true,
      start: startHour.toString(),
      end: endHour.toString(),
    }));
  }

  const startChangeHandler = (e: React.ChangeEvent<HTMLInputElement>): void => {
    setState(s => ({ ...s, start: e.target.value }));
  };
  const endChangeHandler = (e: React.ChangeEvent<HTMLInputElement>): void => {
    setState(s => ({ ...s, end: e.target.value }));
  };

  const submitHandler = (userId: number): void => {
    shiftRoleAssignWorker({
      variables: {
        shiftRoleId: +props.query.shiftRoleId,
        userId,
        from: +state.start,
        to: +state.end,
      },
    })
      .then(() => {
        enqueueSnackbar('Uživatel přiřazen', { variant: 'success' });
        props.redirect(shiftPlannerRoutes.table, {
          dayIndex: props.query.dayIndex,
        });
      })
      .catch(err => {
        if (
          err.graphQLErrors.some(
            e => e.message?.message === apiErrors.shiftRole.hoursOutOfRange,
          )
        ) {
          enqueueSnackbar('Rozsah hodin mimo definované směny', {
            variant: 'warning',
          });
        } else {
          enqueueSnackbar('Nepovedlo se přiřadit uživatele', {
            variant: 'error',
          });
        }
      });
  };

  const unassignHandler = () => {
    shiftRoleUnassignWorker({
      variables: {
        shiftRoleId: +props.query.shiftRoleId,
        from: +state.start,
        to: +state.end,
      },
    })
      .then(() => {
        enqueueSnackbar('Časový úsek úspešně vyprázdněn', {
          variant: 'success',
        });
        props.redirect(shiftPlannerRoutes.table, {
          dayIndex: props.query.dayIndex,
        });
      })
      .catch(err => {
        if (
          err.graphQLErrors.some(
            e => e.message?.message === apiErrors.shiftRole.hoursOutOfRange,
          )
        ) {
          enqueueSnackbar('Rozsah hodin mimo definované směny', {
            variant: 'warning',
          });
        } else {
          enqueueSnackbar('Časový úsek se nepovedlo vyprázdnit', {
            variant: 'error',
          });
        }
      });
  };

  const daysShort = ['po', 'út', 'st', 'čt', 'pá', 'so', 'ne'];

  const date = new Date(
    data?.shiftRoleFindById.shiftDay.shiftWeek.startDay || Date.now(),
  );
  date.setDate(
    date.getDate() +
      dayList.findIndex(d => d === data?.shiftRoleFindById.shiftDay.day) || +0,
  );
  const formattedDate = dateFormat(date, 'd. m.');

  const dayName = capitalize(
    translatedDayList[
      dayList.findIndex(d => d === data?.shiftRoleFindById.shiftDay.day)
    ] || '  ',
  );

  const branch = data?.shiftRoleFindById.shiftDay.shiftWeek.branch;

  const startHourInput = (
    <TextField
      type="number"
      variant="outlined"
      label="start"
      onChange={startChangeHandler}
      value={state.start}
      error={error !== undefined}
    />
  );

  return (
    <>
      <Typography variant="h5">
        {data
          ? `${formattedDate} - ${dayName} - ${branch.name} - ${data?.shiftRoleFindById.type.name}`
          : '-'}
      </Typography>
      <ForceDialog
        open={state.forceModal !== null}
        onSubmit={() => {
          submitHandler(state.forceModal);
        }}
        loading={mutationLoading}
        onClose={() => setState(s => ({ ...s, forceModal: null }))}
      />
      <Grid container spacing={2}>
        <Grid item xs={12} />
        <Grid item container xs={6} spacing={2}>
          <Grid item>
            {data?.shiftRoleFindById.halfHour &&
            data?.shiftRoleFindById.firstHour === +state.start ? (
              <Badge badgeContent="+30" color="primary">
                {startHourInput}
              </Badge>
            ) : (
              startHourInput
            )}
          </Grid>
          <Grid item>
            <TextField
              type="number"
              variant="outlined"
              label="konec"
              onChange={endChangeHandler}
              value={state.end}
              error={error !== undefined}
            />
          </Grid>
        </Grid>
        <Grid item xs={6} justify="flex-end" container>
          <CurrentWorkers
            shiftRole={data?.shiftRoleFindById}
            dayStart={+data?.globalSettingsFindDayStart.value}
          />
        </Grid>
        <Grid xs={12}>
          <Box pl={1}>
            <FormControlLabel
              // prettier-ignore
              control={(
                <Checkbox
                  value={withoutPreferredHours}
                  onChange={(e, checked) => setWithoutPreferredHours(checked)}
                />
              )}
              label="Vybrat všechny bez ohledu na požadavky"
            />
          </Box>
        </Grid>
        <Grid item xs={12}>
          <MaterialTable
            isLoading={loading}
            columns={[
              {
                title: 'Jméno',
                // eslint-disable-next-line react/display-name
                render: (row: RelevantUser) => {
                  const name = `${row.name} ${row.surname}`;
                  const bolded = row.mainBranch ? <b>{name}</b> : <>{name}</>;

                  return (
                    <Box display="flex">
                      {bolded}

                      {data?.shiftRoleFindById.type.useCars && row.hasOwnCar && (
                        <Box pl={1}>
                          <CarIcon fontSize="small" />
                        </Box>
                      )}
                    </Box>
                  );
                },
              },
              {
                title: 'Hodnocení',
                field: 'user.totalEvaluationScore',
                cellStyle: (_, row: RelevantUser) => {
                  const score = row.user.totalEvaluationScore;
                  if (score > 0) return { color: theme.palette.success.main };
                  if (score < 0) return { color: theme.palette.error.main };

                  return {};
                },
                render: (row: RelevantUser) => {
                  const score = row.user.totalEvaluationScore;
                  if (score > 0) {
                    return `+${score}`;
                  }

                  return score;
                },
              },
              {
                title: 'Pobočky',
                field: 'user.branches',
                render: (row: RelevantUser) =>
                  row.user.workingBranches
                    .map(b => b.name.slice(0, 2))
                    .join(', '),
              },
              {
                title: 'Požadavky',
                field: 'preferredHours',
                render: (row: RelevantUser) => {
                  const dayStart = +data?.globalSettingsFindDayStart.value || 8;
                  const hourIntervals = hoursToIntervals(
                    dayStart,
                    row.preferredHours.map(h => h.startHour),
                  );

                  return hourIntervals
                    .map(i => `${i.from}:00-${i.to}:00`)
                    .join(', ');
                },
              },
              {
                title: 'Již pracuje',
                field: 'preferredHours',
                render: (row: RelevantUser) => {
                  const dayStart = +data?.globalSettingsFindDayStart.value || 8;
                  const hourIntervals = hoursToIntervalsByGroup(
                    dayStart,
                    row.preferredHours
                      .filter(p => !p.notAssigned)
                      .map(h => ({
                        startHour: h.startHour,
                        group: h.assignedToBranch.id.toString(),
                      })),
                  );
                  const getBranch = (hour: number) =>
                    row.preferredHours.find(h => h.startHour === hour)
                      .assignedToBranch;

                  return hourIntervals.map(i => (
                    <span
                      key={i.from}
                      className={
                        getBranch(i.from).id !== branch?.id
                          ? classes.afterDeadline
                          : ''
                      }
                    >
                      {`${i.from}:00-${i.to}:00 (${getBranch(i.from).name.slice(
                        0,
                        2,
                      )})`}
                      <br />
                    </span>
                  ));
                },
              },
              {
                title: 'Poslední úprava požadavků',
                // eslint-disable-next-line react/display-name
                render: (row: RelevantUser) => {
                  const dayShort =
                    daysShort[
                      (new Date(row?.lastPreferredTime).getDay() + 6) % 7
                    ];
                  const formattedDate = dateFormat(
                    row?.lastPreferredTime,
                    'dd.mm. HH:MM',
                  );

                  return (
                    <span
                      className={row.afterDeadline ? classes.afterDeadline : ''}
                    >
                      {row?.lastPreferredTime
                        ? `${dayShort} ${formattedDate}`
                        : '-'}
                    </span>
                  );
                },
              },
              {
                title: 'Shoda požadavků',
                render: (row: RelevantUser) =>
                  row.perfectMatch ? 'Úplná' : 'Částečná',
                hidden: withoutPreferredHours,
              },
              {
                title: 'Tento týden hodin/požadavky',
                render: (row: RelevantUser) => {
                  let percents = 0;
                  if (row.totalWeekHours !== 0) {
                    percents = Math.round(
                      (row.totalWeekHours / row.totalPreferredHours) * 100,
                    );
                  }

                  return `${row.totalWeekHours}/${
                    row.totalPreferredHours
                  } (${`${percents}%`})`;
                },
              },
            ]}
            data={data?.relevantUserFindAllForShiftRole}
            options={{
              sorting: false,
            }}
            actions={[
              {
                icon: AddIcon,
                disabled: loading,
                tooltip: 'Zvolit',
                onClick: (e, d: RelevantUser) => {
                  if (d.perfectMatch) {
                    submitHandler(d.id);
                  } else {
                    setState(s => ({
                      ...s,
                      forceModal: d.id,
                    }));
                  }
                },
              },
              {
                icon: PersonIcon,
                disabled: loading,
                tooltip: 'Detail uživatele',
                onClick: (e, user: RelevantUser) =>
                  window.open(
                    `${routes.users.userDetail}?userId=${user.id}`,
                    '_blank',
                  ),
                hidden: !canSeeUser,
              },
              {
                icon: StarRateIcon,
                disabled: loading,
                tooltip: 'Hodnocení',
                onClick: (e, user: RelevantUser) =>
                  window.open(
                    `${routes.evaluation.detail}?id=${user.id}`,
                    '_blank',
                  ),
                hidden: !canSeeEvaluation,
              },
            ]}
          />
        </Grid>
        <Grid item xs={12}>
          <Actions
            actions={[
              {
                id: 1,
                element: (
                  <LoadingButton
                    color="primary"
                    variant="contained"
                    loading={mutationLoading || unassginLoading}
                    onClick={unassignHandler}
                  >
                    Vyprázdnit
                  </LoadingButton>
                ),
              },
              {
                id: 2,
                element: (
                  <LoadingButton
                    color="secondary"
                    variant="contained"
                    loading={mutationLoading || unassginLoading}
                    // prettier-ignore
                    onClick={() => props.redirect(shiftPlannerRoutes.table, { dayIndex: props.query.dayIndex })}
                  >
                    Zrušit
                  </LoadingButton>
                ),
              },
            ]}
          />
        </Grid>
      </Grid>
    </>
  );
};

export default AssignWorker;
