import { useQuery } from '@apollo/react-hooks';
import React, { useEffect, useState } from 'react';
import dayjs from 'dayjs';

import EnteredPreferredWeeksUI from 'components/EnteredPreferredWeeks/EnteredPreferredWeeksUI';

import Paper from '../Paper';

import PREFERRED_WEEK_FIND_ALL_IN_WEEK from './queries/preferredWeeks';
import {
  EnteredPreferredWeeksProps,
  PreferredHour,
  PreferredWeek,
  PreferredWeekFindAllInWeek,
  PreferredWeekFindAllInWeekVariables,
  PreferredWeekWithPercents,
} from './types';

const EnteredPreferredWeeks: React.FC<EnteredPreferredWeeksProps> = props => {
  const { data: preferredWeekData, loading: preferredWeekLoading } = useQuery<
    PreferredWeekFindAllInWeek,
    PreferredWeekFindAllInWeekVariables
  >(PREFERRED_WEEK_FIND_ALL_IN_WEEK, {
    variables: { skipWeeks: props.skipWeeks },
    fetchPolicy: 'no-cache',
  });

  const dayStart = +preferredWeekData?.globalSettingsFindDayStart.value || 8;

  const preferredWeeks = preferredWeekData?.preferredWeekFindAllInWeek || [];
  const getPreferredWeekValue = (week: PreferredWeek): number => {
    let value = 0;
    week.preferredDays.forEach(d => {
      if (d.preferredHours.length > 0) {
        value++;
      }
    });

    return value;
  };
  preferredWeeks.sort((week1, week2) => {
    const value1 = getPreferredWeekValue(week1);
    const value2 = getPreferredWeekValue(week2);

    if (value1 < value2) {
      return 1;
    }
    if (value1 > value2) {
      return -1;
    }

    return 0;
  });

  let date = Date.now().toString();
  if (preferredWeekData?.preferredWeekFindAllInWeek) {
    date = preferredWeekData.preferredWeekFindAllInWeek[0]?.startDay;
  }
  const endDate = dayjs(date).add(6, 'day');

  const getAllPreferredHours = (preferredWeek: PreferredWeek) => {
    const allPreferredHours: PreferredHour[] = [];
    preferredWeek.preferredDays.forEach(d => {
      allPreferredHours.push(...d.preferredHours);
    });

    return allPreferredHours;
  };

  const getAllUsedPreferredHours = (allPreferredHours: PreferredHour[]) => {
    return allPreferredHours.filter(h => !h.notAssigned);
  };

  const getPercents = (preferredWeek: PreferredWeek): number => {
    const allPreferredHours = getAllPreferredHours(preferredWeek);

    const usedPreferredHours = getAllUsedPreferredHours(allPreferredHours);

    const visibleAllPreferredHours = allPreferredHours.filter(h => h.visible);

    if (visibleAllPreferredHours.length === 0) return 100;
    if (usedPreferredHours.length === 0) return 0;

    return Math.round(
      100 / (visibleAllPreferredHours.length / usedPreferredHours.length),
    );
  };

  const weeksWithPercents: PreferredWeekWithPercents[] =
    preferredWeekData?.preferredWeekFindAllInWeek || [];
  weeksWithPercents.forEach(week => {
    const allPreferredHours = getAllPreferredHours(week);
    week.percents = getPercents(week);
    week.totalPreferredHours = allPreferredHours.filter(h => h.visible).length;
    week.totalUsedPreferredHours = getAllUsedPreferredHours(
      allPreferredHours,
    ).length;
  });

  return (
    <Paper
      title={`${props.title} -  ${dayjs(date).format(
        'DD. MM. YYYY',
      )} - ${endDate.format('DD. MM. YYYY')}`}
    >
      <EnteredPreferredWeeksUI
        preferredWeeks={preferredWeekData?.preferredWeekFindAllInWeek || []}
        dayStart={dayStart}
        loading={preferredWeekLoading}
        branches={preferredWeekData?.branchFindAll || []}
        shiftRoleTypes={preferredWeekData?.shiftRoleTypeFindAll || []}
      />
    </Paper>
  );
};

export default EnteredPreferredWeeks;
