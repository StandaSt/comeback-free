import { useLazyQuery, useQuery } from '@apollo/react-hooks';
import { Grid, Typography } from '@material-ui/core';
import { gql } from 'apollo-boost';
import React, { useState } from 'react';
import dayjs from 'dayjs';

import dayList from 'components/dayList';
import OverlayLoading from 'components/OverlayLoading';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';
import CsvDownload from 'components/ShiftWeekSummary/csvDownload';
import ShiftWeekSummary from 'components/ShiftWeekSummary/shiftWeekSummary';

import Paper from '../Paper';

import BranchSelect from './branchSelect';
import {
  BranchGetShiftWeeks,
  BranchGetShiftWeeksVars,
  GlobalData,
  GlobalDataVariables,
  ShiftDay,
  ShiftWeekSummaryPropsIndex,
  UserMapValue,
} from './types';

const GLOBAL_DATA = gql`
  query($skipWeeks: Int!) {
    userGetLogged {
      planableBranches {
        id
        name
      }
    }
    globalSettingsFindDayStart {
      id
      value
    }
    shiftWeekGetStartDay(skipWeeks: $skipWeeks)
  }
`;
const BRANCH_GET_SHIFT_WEEKS = gql`
  query($branchId: Int!, $skipWeeks: Int!) {
    branchGetShiftWeek(skipWeeks: $skipWeeks, branchId: $branchId) {
      id
      published
      branch {
        id
        name
      }
      shiftDays {
        id
        day
        shiftRoles {
          id
          halfHour
          type {
            id
            name
          }
          shiftHours {
            id
            isFirst
            startHour
            confirmed
            employee {
              id
              name
              surname
            }
          }
        }
      }
    }
  }
`;

const ShiftWeekSummaryIndex: React.FC<ShiftWeekSummaryPropsIndex> = props => {
  const [branch, setBranch] = useState<number | null>(null);
  const { data: globalData, loading: globalLoading } = useQuery<
    GlobalData,
    GlobalDataVariables
  >(GLOBAL_DATA, {
    fetchPolicy: 'no-cache',
    variables: {
      skipWeeks: props.skipWeeks,
    },
  });
  const [fetchData, { data, loading }] = useLazyQuery<
    BranchGetShiftWeeks,
    BranchGetShiftWeeksVars
  >(BRANCH_GET_SHIFT_WEEKS, {
    variables: { branchId: branch, skipWeeks: props.skipWeeks },
    fetchPolicy: 'no-cache',
  });

  const users = new Map<number, UserMapValue>();

  if (data) {
    const week = data?.branchGetShiftWeek;

    dayList.forEach(dayName => {
      const day: ShiftDay = week.shiftDays.find(d => d.day === dayName);
      day.shiftRoles.forEach(role => {
        role.shiftHours.forEach(hour => {
          if (hour.employee) {
            const mapKey = hour.employee.id;
            let defaultValue: UserMapValue = users.get(mapKey);
            if (!defaultValue) {
              defaultValue = {
                name: hour.employee.name,
                surname: hour.employee.surname,
                confirmed: false,
                monday: [],
                tuesday: [],
                wednesday: [],
                thursday: [],
                friday: [],
                saturday: [],
                sunday: [],
                shiftRoleTypesIndexes: [],
              };
            }

            const shiftRoleTypesIndexes = [
              ...defaultValue.shiftRoleTypesIndexes,
            ];

            if (!shiftRoleTypesIndexes.some(s => s === role.type.id)) {
              shiftRoleTypesIndexes.push(role.type.id);
            }

            const mapValue: UserMapValue = {
              ...defaultValue,
              [dayName]: [
                ...defaultValue[dayName],
                {
                  startHour: hour.startHour,
                  shiftRoleType: role.type.name,
                  halfHour: role.halfHour && hour.isFirst,
                },
              ],
              confirmed: hour.confirmed === true,
              shiftRoleTypesIndexes,
            };
            users.set(mapKey, mapValue);
          }
        });
      });
    });
  }

  const startDate = dayjs(globalData?.shiftWeekGetStartDay);
  const endDate = startDate.add(6, 'day');

  return (
    <Paper
      title={`${props.title} - ${startDate.format(
        'DD. MM. YYYY',
      )} - ${endDate.format('DD. MM. YYYY')}`}
    >
      <OverlayLoadingContainer>
        <OverlayLoading loading={loading || globalLoading} />

        <Grid container spacing={2} alignItems="center">
          <Grid item xs={12} />
          <Grid item xs={6}>
            <BranchSelect
              selected={branch || ''}
              branches={globalData?.userGetLogged.planableBranches || []}
              onChange={(id: number) => {
                setBranch(id);
                fetchData();
              }}
            />
          </Grid>
          <Grid item xs={6} container justify="flex-end">
            <CsvDownload
              users={users}
              dayStart={+globalData?.globalSettingsFindDayStart.value || 8}
              disabled={branch === null}
              branchName={data?.branchGetShiftWeek[0]?.branch.name || ''}
            />
          </Grid>
          {data?.branchGetShiftWeek.published ? (
            <ShiftWeekSummary
              users={users}
              dayStart={+globalData?.globalSettingsFindDayStart.value || 8}
            />
          ) : (
            <>
              {branch !== null && (
                <Grid item>
                  <Typography variant="h6">
                    Tato pobočka ještě není publikovaná
                  </Typography>
                </Grid>
              )}
            </>
          )}
        </Grid>
      </OverlayLoadingContainer>
    </Paper>
  );
};

export default ShiftWeekSummaryIndex;
