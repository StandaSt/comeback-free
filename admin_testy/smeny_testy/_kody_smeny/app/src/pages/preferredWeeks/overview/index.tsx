import { useLazyQuery } from '@apollo/react-hooks';
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@material-ui/core';
import { gql } from 'apollo-boost';
import Link from 'next/link';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React, { useState } from 'react';

import preferredWeeksResources from 'pages/preferredWeeks/index/resources';
import overviewBreadcrumbs from 'pages/preferredWeeks/overview/breadcrumbs';
import dayList from 'components/dayList';
import hoursToIntervals from 'components/hoursToIntervals';
import Paper from 'components/Paper';
import PreferredWeekSummary from 'components/PreferredWeekSummary';
import { SummaryDay } from 'components/PreferredWeekSummary/types';
import withPage from 'components/withPage';

import {
  PreferredDay,
  PreferredWeekFindById,
  PreferredWeekFindByIdVars,
} from './types';

const PREFERRED_WEEK_FIND_BY_ID = gql`
  fragment Day on PreferredDay {
    id
    day
    preferredHours {
      id
      startHour
      visible
    }
  }
  query($id: Int!) {
    preferredWeekFindById(id: $id) {
      id
      startDay
      preferredDays {
        ...Day
      }
    }
    globalSettingsFindDayStart {
      id
      value
    }
    globalSettingsFindPreferredDeadline {
      id
      value
    }
  }
`;

const Overview = () => {
  const router = useRouter();
  const [preferredWeekFindById, { data, loading, called }] = useLazyQuery<
    PreferredWeekFindById,
    PreferredWeekFindByIdVars
  >(PREFERRED_WEEK_FIND_BY_ID);
  const [modal, setModal] = useState(false);

  if (router.query.id && !called) {
    preferredWeekFindById({ variables: { id: +router.query.id } });
  }

  const translatedDaysList = [
    'Pondělí',
    'Úterý',
    'Středa',
    'Čtvrtek',
    'Pátek',
    'Sobota',
    'Neděle',
  ];

  const formatDate = (date: Date): string => dateFormat(date, 'd.m');

  const summaryDays: SummaryDay[] = [];

  if (data) {
    dayList.forEach((dayName, index) => {
      const day: PreferredDay = data?.preferredWeekFindById.preferredDays.find(
        pd => pd.day === dayName,
      );
      const dayDate = new Date(data.preferredWeekFindById.startDay);
      dayDate.setDate(dayDate.getDate() + index);
      const dayStartTime = `${formatDate(dayDate)} - ${
        translatedDaysList[index]
      }`;

      const hourIntervals = hoursToIntervals(
        +data.globalSettingsFindDayStart.value,
        day.preferredHours.filter(ph => ph.visible).map(h => h.startHour),
      );
      const dayStart = hourIntervals[0]?.from.toString() || '';
      const dayEnd = hourIntervals[0]?.to.toString() || '';

      summaryDays.push({
        name: dayStartTime,
        start: dayStart,
        end: dayEnd,
        order: index,
      });
    });
  }

  const editURL = {
    pathname: routes.preferredWeeks.week,
    query: { id: router.query.id },
  };

  const editHandler = () => {
    const now = new Date(Date.now());
    const startDay = new Date(data?.preferredWeekFindById.startDay);
    const deadline = new Date(data?.globalSettingsFindPreferredDeadline.value);
    const relativeDeadline = new Date(startDay);

    relativeDeadline.setDate(
      relativeDeadline.getDate() + ((deadline.getDay() + 6) % 7) - 7,
    );
    relativeDeadline.setHours(deadline.getHours());

    const afterDeadline = relativeDeadline.getTime() < now.getTime();

    if (afterDeadline) {
      setModal(true);
    } else {
      router.push(editURL);
    }
  };

  return (
    <Paper
      title="Přehled požadavků"
      loading={loading}
      actions={[
        <Button
          key="editAction"
          color="primary"
          variant="contained"
          onClick={editHandler}
        >
          Upravit
        </Button>,
      ]}
    >
      <PreferredWeekSummary days={summaryDays} />
      <Dialog open={modal}>
        <DialogTitle>Hodláte upravit požadavky po deadlinu</DialogTitle>
        <DialogContent>
          <DialogContentText>
            Pokud upravíte požadavek po deadlinu, klesnete v seznamu. To může
            mít za následek, že bude mít méně směn.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Link href={editURL}>
            <Button color="primary">Upravit</Button>
          </Link>
          <Button color="secondary" onClick={() => setModal(false)}>
            Zrušit
          </Button>
        </DialogActions>
      </Dialog>
    </Paper>
  );
};

export default withPage(Overview, overviewBreadcrumbs, preferredWeeksResources);
