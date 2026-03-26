import { useMutation, useQuery } from '@apollo/react-hooks';
import { Typography } from '@material-ui/core';
import capitalize from '@material-ui/core/utils/capitalize';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import apiErrors from '@shift-planner/shared/config/api/errors';
import dateFormat from 'dateformat';
import React, { useState } from 'react';

import Actions from 'components/Actions';
import dayList, { translatedDayList } from 'components/dayList';
import hourIntervalChecker from 'components/hourIntervalChecker';
import hoursToIntervals from 'components/hoursToIntervals';
import LoadingButton from 'components/LoadingButton';
import OverlayLoading from 'components/OverlayLoading';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';
import shiftHoursFragment from 'components/ShiftPlanner/fragments/shiftHoursFragment';
import shiftRoleTypeFragment from 'components/ShiftPlanner/fragments/shiftRoleTypeFragment';
import shiftPlannerRoutes from 'components/ShiftPlanner/routes';

import EditRole from './editRole';
import {
  EditRoleIndexProps,
  ShiftRoleChangeHours,
  ShiftRoleChangeHoursVars,
  ShiftRoleFindById,
  ShiftRoleFindByIdVars,
  ShiftRoleRemove,
  ShiftRoleRemoveVars,
} from './types';

const SHIFT_ROLE_FIND_BY_ID = gql`
  query($id: Int!) {
    shiftRoleFindById(id: $id) {
      id
      halfHour
      type {
        id
      }
      shiftHours {
        id
        startHour
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
    shiftRoleTypeFindAll {
      id
      name
    }
    globalSettingsFindDayStart {
      id
      value
    }
  }
`;

const SHIFT_ROLE_CHANGE_HOURS = gql`
  ${shiftHoursFragment}
  ${shiftRoleTypeFragment}
  mutation($id: Int!, $hours: [HourArg!]!, $typeId: Int!, $halfHour: Boolean!) {
    shiftRoleEdit(
      id: $id
      hours: $hours
      typeId: $typeId
      halfHour: $halfHour
    ) {
      id
      ...ShiftRoleType
      ...ShiftHours
    }
  }
`;

const SHIFT_ROLE_REMOVE = gql`
  mutation($id: Int!) {
    shiftRoleRemove(id: $id)
  }
`;

const EditRoleIndex = (props: EditRoleIndexProps) => {
  const [state, setState] = useState({
    updated: false,
    type: -1,
    hours: [{ id: 0, from: '', to: '' }],
    hourId: 1,
    halfHour: false,
  });
  const { data, loading } = useQuery<ShiftRoleFindById, ShiftRoleFindByIdVars>(
    SHIFT_ROLE_FIND_BY_ID,
    { variables: { id: +props.query?.roleId || -1 }, fetchPolicy: 'no-cache' },
  );
  const [shiftRoleChangeHours, { loading: changeHoursLoading }] = useMutation<
    ShiftRoleChangeHours,
    ShiftRoleChangeHoursVars
  >(SHIFT_ROLE_CHANGE_HOURS);
  const [shiftRoleRemove, { loading: removeLoading }] = useMutation<
    ShiftRoleRemove,
    ShiftRoleRemoveVars
  >(SHIFT_ROLE_REMOVE);

  const dayStart = +data?.globalSettingsFindDayStart.value;

  if (data && !state.updated) {
    const hours = {};
    data.shiftRoleFindById.shiftHours.forEach(h => {
      hours[h.startHour] = h.id;
    });

    const hourGroups = hoursToIntervals(
      dayStart,
      data.shiftRoleFindById.shiftHours.map(h => h.startHour),
    );

    const mappedHourGroups = hourGroups.map(g => ({
      id: g.id,
      to: g.to.toString(),
      from: g.from.toString(),
    }));

    setState(s => ({
      ...s,
      updated: true,
      type: data?.shiftRoleFindById.type.id,
      hours: mappedHourGroups,
      halfHour: data?.shiftRoleFindById.halfHour,
    }));
  }

  const roleTypeChangeHandler = (id: number) => {
    setState(s => ({ ...s, type: id }));
  };

  const hourAddHandler = () => {
    setState(s => ({
      ...s,
      hours: [...s.hours, { id: s.hourId, from: '', to: '' }],
      hourId: s.hourId + 1,
    }));
  };

  const hourRemoveHandler = (id: number) => {
    setState(s => {
      const hours = s.hours.filter(h => h.id !== id);

      return { ...s, hours };
    });
  };

  const hourChangeHandler = (id: number, from: string, to: string) => {
    setState(s => {
      const { hours } = s;
      const hour = hours.find(h => h.id === id);
      if (hour) {
        hour.from = from;
        hour.to = to;
      }

      return { ...s, hours };
    });
  };

  const submitHandler = () => {
    const hours = [];
    let error = false;
    state.hours.forEach(hour => {
      if (
        !hourIntervalChecker(hour.from.toString(), hour.to.toString(), dayStart)
      ) {
        error = true;
      }
      if (
        hourIntervalChecker(hour.from.toString(), hour.to.toString(), dayStart)
      ) {
        for (let i = +hour.from; i !== +hour.to; i++) {
          if (i > 23) {
            i = 0;
            if (+hour.to === 0) break;
          }

          hours.push({ startHour: i });
        }
      } else {
        error = true;
      }
    });
    if (!error) {
      shiftRoleChangeHours({
        variables: {
          id: +props.query.roleId,
          hours,
          typeId: state.type,
          halfHour: state.halfHour,
        },
      })
        .then(() => {
          props.enqueueSnackbar('Slot úspěšně upraven', { variant: 'success' });
          props.refetchWeek();
          props.redirect(shiftPlannerRoutes.table, {
            dayIndex: props.query.dayIndex,
          });
        })
        .catch(err => {
          if (
            err.graphQLErrors.some(
              e => e.message.message === apiErrors.shiftRole.notEmpty,
            )
          ) {
            props.enqueueSnackbar(
              'Ve slotu nesmí být přiřazeni žádní pracovníci',
              {
                variant: 'warning',
              },
            );
          } else
            props.enqueueSnackbar('Slot se nepovedlo upravit', {
              variant: 'error',
            });
        });
    } else {
      props.enqueueSnackbar('Zadané hodnoty nejsou validní', {
        variant: 'warning',
      });
    }
  };

  const removeHandler = (): void => {
    shiftRoleRemove({ variables: { id: +props.query.roleId } })
      .then(() => {
        props.enqueueSnackbar('Slot úspěšně odstraněn', { variant: 'success' });
        props.refetchWeek();
        props.redirect(shiftPlannerRoutes.table, {
          dayIndex: props.query.dayIndex,
        });
      })
      .catch(error => {
        if (
          error.graphQLErrors.some(
            e => e.message.message === apiErrors.shiftRole.notEmpty,
          )
        ) {
          props.enqueueSnackbar(
            'Ve slotu nesmí být přiřazeni žádní pracovníci',
            {
              variant: 'warning',
            },
          );
        } else {
          props.enqueueSnackbar('Slot se nepovedlo odstranit', {
            variant: 'error',
          });
        }
      });
  };

  const halfHourChangeHandler = (checked: boolean): void => {
    setState({ ...state, halfHour: checked });
  };

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

  return (
    <OverlayLoadingContainer>
      <OverlayLoading loading={loading} />
      <Typography variant="h5">
        {`${!props.hideDate ? `${formattedDate} -` : ''} ${dayName} - ${
          data?.shiftRoleFindById.shiftDay.shiftWeek.branch.name
        }`}
      </Typography>
      <EditRole
        roleTypes={data?.shiftRoleTypeFindAll}
        currentRoleType={state.type}
        hours={state.hours}
        halfHour={state.halfHour}
        onHalfHourChange={halfHourChangeHandler}
        onRoleTypeChange={roleTypeChangeHandler}
        onHourAdd={hourAddHandler}
        onHourRemove={hourRemoveHandler}
        onHourChange={hourChangeHandler}
      />
      <Actions
        actions={[
          {
            id: 0,
            element: (
              <LoadingButton
                color="secondary"
                variant="contained"
                loading={loading || removeLoading}
                onClick={removeHandler}
              >
                Odstranit
              </LoadingButton>
            ),
          },
          {
            id: 1,
            element: (
              <LoadingButton
                color="primary"
                variant="contained"
                loading={loading || changeHoursLoading}
                onClick={submitHandler}
              >
                Uložit
              </LoadingButton>
            ),
          },
        ]}
      />
    </OverlayLoadingContainer>
  );
};

export default withSnackbar(EditRoleIndex);
