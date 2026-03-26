import { useLazyQuery, useMutation } from '@apollo/react-hooks';
import { makeStyles, Theme } from '@material-ui/core/styles';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React, { useState } from 'react';

import preferredWeeksResources from 'pages/preferredWeeks/index/resources';
import Actions from 'components/Actions';
import dayList from 'components/dayList';
import hourIntervalChecker from 'components/hourIntervalChecker';
import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import PreferredWeekSummary from 'components/PreferredWeekSummary';
import WeekStepper from 'components/WeekStepper';
import withPage from 'components/withPage';

import preferredDayInfoFragment from './fragments/preferredDayInfo';
import weekBreadcrumbs from './breadcrumbs';
import Day from './day';
import {
  DayHours,
  DayT,
  PreferredDay,
  PreferredDayChangeHours,
  PreferredDayChangeHoursVars,
  PreferredWeekFindById,
  PreferredWeekFindByIdVars,
  WeekProps,
} from './types';

const PREFERRED_WEEK_FIND_BY_ID = gql`
  ${preferredDayInfoFragment}
  query($id: Int!) {
    preferredWeekFindById(id: $id) {
      startDay
      preferredDays {
        ...PreferredDayInfo
      }
    }
    globalSettingsFindDayStart {
      id
      value
    }
  }
`;

const PREFERRED_DAY_CHANGE_HOURS = gql`
  ${preferredDayInfoFragment}
  mutation($dayHours: [DayHour!]!) {
    preferredDayChangeHours(dayHours: $dayHours) {
      ...PreferredDayInfo
    }
  }
`;

const useStyles = makeStyles((theme: Theme) => ({
  submitButton: {
    marginTop: theme.spacing(20),
  },
}));

const Week = (props: WeekProps) => {
  const classes = useStyles();
  const router = useRouter();
  const [preferredWeekFindById, { data, loading, error }] = useLazyQuery<
    PreferredWeekFindById,
    PreferredWeekFindByIdVars
  >(PREFERRED_WEEK_FIND_BY_ID, { fetchPolicy: 'no-cache' });
  const [preferredDayChangeHours, { loading: mutationLoading }] = useMutation<
    PreferredDayChangeHours,
    PreferredDayChangeHoursVars
  >(PREFERRED_DAY_CHANGE_HOURS);
  const [day, setDay] = useState(0);
  const [week, setWeek] = useState<DayT[]>([]);
  const [updated, setUpdated] = useState(false);
  const [summary, setSummary] = useState(false);

  if (router.query.id && !data && !error && !loading) {
    preferredWeekFindById({ variables: { id: +router.query.id } });
  }

  if (!updated && data) {
    setUpdated(true);
    const temporaryWeek: DayT[] = [];
    dayList.forEach((d, index) => {
      const currentDay: PreferredDay = data.preferredWeekFindById.preferredDays.find(
        pd => pd.day === d,
      );
      let start = '';
      let end = '';

      if (currentDay) {
        let notEqualTo = +data.globalSettingsFindDayStart.value - 1;
        if (notEqualTo < 0) {
          notEqualTo = 23;
        }
        for (
          let i = +data.globalSettingsFindDayStart.value;
          i !== notEqualTo;
          i++
        ) {
          if (i === 24) i = 0;

          const hour = currentDay?.preferredHours
            .filter(ph => ph.visible)
            .find(h => h.startHour === i);
          if (start === '' && hour) {
            start = hour.startHour.toString() || '';
          }
          if (hour) {
            end = hour.startHour.toString() || '';
          }
        }

        if (end === '23') end = '0';
        else if (end !== '') end = (+end + 1).toString();

        temporaryWeek[index] = { start, end, id: currentDay.id };
      }
    });
    setWeek(temporaryWeek);
  }

  const translatedDays = [
    'Pondělí',
    'Úterý',
    'Středa',
    'Čtvrtek',
    'Pátek',
    'Sobota',
    'Neděle',
  ];

  const currentDay: PreferredDay = data?.preferredWeekFindById[dayList[day]];

  const submitHandler = () => {
    const dayHours: DayHours[] = [];

    let err = false;

    week.forEach(d => {
      if (d) {
        const hours = [];
        if (
          !hourIntervalChecker(
            d.start,
            d.end,
            +data?.globalSettingsFindDayStart.value || 8,
          )
        ) {
          err = true;
        }
        if (!err && d.start !== '' && d.end !== '') {
          for (let h = +d.start; h !== +d.end; h++) {
            if (h === 24) {
              h = 0;
              if (+d.end === 0) break;
            }
            hours.push(h);
          }
        }
        dayHours.push({ dayId: d.id, hours });
      }
    });

    if (err) {
      props.enqueueSnackbar('Hodiny jsou mimo povolený rozsah', {
        variant: 'warning',
      });

      return;
    }

    preferredDayChangeHours({ variables: { dayHours } })
      .then(() => {
        props.enqueueSnackbar('Týdenní požadavky úspěšně uloženy', {
          variant: 'success',
        });
        router.push(routes.preferredWeeks.index);
      })
      .catch(() => {
        props.enqueueSnackbar('Nepovedlo se uložit týdenní požadavky', {
          variant: 'error',
        });
      });
  };

  const startChangeHandler = (start: string) => {
    setWeek(h => {
      const hours = [...h];
      if (!hours[day]) hours[day] = { start, end: '8', id: currentDay.id };
      else hours[day].start = start;

      return hours;
    });
  };
  const endChangeHandler = (end: string) => {
    setWeek(h => {
      const hours = [...h];
      if (!hours[day]) hours[day] = { start: '8', end, id: currentDay.id };
      else hours[day].end = end;

      return hours;
    });
  };

  const formatDate = (date: Date): string => dateFormat(date, 'd.m');

  const getDayDate = (dayOrder: number): string => {
    const date = data
      ? new Date(data?.preferredWeekFindById.startDay)
      : new Date();
    date.setDate(date.getDate() + dayOrder);

    return formatDate(date);
  };

  const weekHour = week[day];
  const start = weekHour?.start;
  const end = weekHour?.end;

  const summaryDays = week.map((w, index) => ({
    start: w.start,
    end: w.end,
    order: index,
    name: `${getDayDate(index)} - ${translatedDays[index]}`,
  }));

  const hourError = !hourIntervalChecker(
    start,
    end,
    +data?.globalSettingsFindDayStart.value || 8,
  );

  return (
    <Paper loading={loading}>
      {!summary && (
        <>
          <WeekStepper
            onDayChange={(d: number) => {
              setDay(d);
            }}
            defaultDay={day}
            buttonsDisabled={hourError}
          />
          <Day
            name={`${getDayDate(day)} - ${translatedDays[day]}`}
            start={start}
            end={end}
            onStartChange={startChangeHandler}
            onEndChange={endChangeHandler}
            error={hourError}
          />
        </>
      )}
      {summary && <PreferredWeekSummary days={summaryDays} />}
      <Actions
        actions={
          !summary
            ? [
                {
                  id: 0,
                  element: (
                    <LoadingButton
                      className={classes.submitButton}
                      key="saveAction"
                      disabled={hourError}
                      onClick={() => setSummary(true)}
                      loading={loading}
                      color="primary"
                      variant={day === 6 ? 'contained' : 'text'}
                    >
                      Ukončit zadávání požadavků
                    </LoadingButton>
                  ),
                },
              ]
            : [
                {
                  id: 0,
                  element: (
                    <LoadingButton
                      key="saveAction"
                      onClick={submitHandler}
                      loading={mutationLoading}
                      color="primary"
                      variant="contained"
                    >
                      Uložit
                    </LoadingButton>
                  ),
                },
                {
                  id: 1,
                  element: (
                    <LoadingButton
                      key="saveAction"
                      onClick={() => setSummary(false)}
                      loading={mutationLoading}
                      color="secondary"
                      variant="contained"
                    >
                      Zpět
                    </LoadingButton>
                  ),
                },
              ]
        }
      />
    </Paper>
  );
};

export default withPage(
  withSnackbar(Week),
  weekBreadcrumbs,
  preferredWeeksResources,
);
