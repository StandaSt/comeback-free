import { useMutation, useQuery } from '@apollo/react-hooks';
import { Typography } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import dateFormat from 'dateformat';
import React, { useState } from 'react';

import Actions from 'components/Actions';
import capitalize from 'components/capitalize';
import dayList, { translatedDayList } from 'components/dayList';
import LoadingButton from 'components/LoadingButton';
import OverlayLoading from 'components/OverlayLoading';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';
import shiftDayFragment from 'components/ShiftPlanner/fragments/shiftDayFragment';
import shiftPlannerRoutes from 'components/ShiftPlanner/routes';

import AddRole from './addRole';
import {
  AddRoleIndexProps,
  ShiftDayAddRole,
  ShiftDayAddRoleVars,
  ShiftRoleTypeFindAll,
  ShiftRoleTypeFindAllVariables,
  State,
} from './types';

const SHIFT_DAY_ADD_ROLE = gql`
  ${shiftDayFragment}
  mutation($id: Int!, $typeId: Int!, $hours: [HourArg!], $halfHour: Boolean!) {
    shiftDayAddRole(
      id: $id
      typeId: $typeId
      hours: $hours
      halfHour: $halfHour
    ) {
      ...ShiftDay
    }
  }
`;

const SHIFT_ROLE_TYPE_FIND_ALL = gql`
  query($shiftDayId: Int!) {
    shiftRoleTypeFindAll {
      id
      name
    }
    globalSettingsFindDayStart {
      id
      value
    }
    shiftDayFindById(id: $shiftDayId) {
      id
      day
      shiftWeek {
        startDay
        branch {
          id
          name
        }
      }
    }
  }
`;

const AddRoleIndex = (props: AddRoleIndexProps) => {
  const [shiftDayAddRole, { loading }] = useMutation<
    ShiftDayAddRole,
    ShiftDayAddRoleVars
  >(SHIFT_DAY_ADD_ROLE);
  const { data, loading: shiftRoleTypeLoading } = useQuery<
    ShiftRoleTypeFindAll,
    ShiftRoleTypeFindAllVariables
  >(SHIFT_ROLE_TYPE_FIND_ALL, {
    variables: { shiftDayId: +props.query.shiftDayId },
    fetchPolicy: 'no-cache',
  });

  const [state, setState] = useState<State>({
    name: '',
    hours: [{ id: 0, from: '', to: '' }],
    hourId: 1,
    roleType: -1,
    halfHour: false,
  });

  const nameChangeHandler = (e: React.ChangeEvent<HTMLInputElement>) => {
    setState({ ...state, name: e.target.value });
  };

  const hourChangeHandler = (id: number, from: string, to: string) => {
    const { hours } = state;
    const hour = hours.find(h => h.id === id);
    if (hour) {
      hour.from = from;
      hour.to = to;
      setState({ ...state, hours });
    }
  };

  const hourAddHandler = () => {
    const { hours } = state;
    hours.push({ id: state.hourId, from: '', to: '' });
    setState({ ...state, hours, hourId: state.hourId + 1 });
  };

  const hourRemoveHandler = (id: number) => {
    const { hours } = state;
    const hourIndex = hours.findIndex(h => h.id === id);
    hours.splice(hourIndex, 1);
    setState({ ...state, hours });
  };

  const submitHandler = () => {
    const hours = [];
    let error = false;
    state.hours.forEach(hour => {
      if (
        +hour.to >= 0 &&
        +hour.to <= 23 &&
        +hour.from >= 0 &&
        +hour.from <= 23
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
    if (state.roleType >= 0) {
      if (!error) {
        shiftDayAddRole({
          variables: {
            id: +props.query.shiftDayId,
            typeId: state.roleType,
            hours,
            halfHour: state.halfHour,
          },
        })
          .then(res => {
            if (res.data) {
              props.enqueueSnackbar('Slot úspěšně přidán', {
                variant: 'success',
              });
              props.redirect(shiftPlannerRoutes.table, {
                dayIndex: props.query.dayIndex,
              });
              props.refetchWeek();
            }
          })
          .catch(() => {
            props.enqueueSnackbar('Nepovedlo se přidat slot', {
              variant: 'error',
            });
          });
      } else {
        props.enqueueSnackbar('Zadané hodnoty nejsou validní', {
          variant: 'warning',
        });
      }
    } else {
      props.enqueueSnackbar('Vyberte typ slotu', { variant: 'warning' });
    }
  };

  const roleTypeChangeHandler = (id: number) => {
    setState({ ...state, roleType: id });
  };

  const halHourChangeHandler = (checked: boolean): void => {
    setState({ ...state, halfHour: checked });
  };

  const date = new Date(
    data?.shiftDayFindById.shiftWeek.startDay || Date.now(),
  );
  date.setDate(
    date.getDate() + dayList.findIndex(d => d === data?.shiftDayFindById.day) ||
      +0,
  );
  const formattedDate = dateFormat(date, 'd. m.');

  const dayName = capitalize(
    translatedDayList[
      dayList.findIndex(d => d === data?.shiftDayFindById.day)
    ] || '  ',
  );

  return (
    <OverlayLoadingContainer>
      <OverlayLoading loading={shiftRoleTypeLoading} />
      <Typography variant="h5">
        {`${!props.hideDate ? `${formattedDate} -` : ''} ${dayName} - ${
          data?.shiftDayFindById.shiftWeek.branch.name
        }`}
      </Typography>
      <AddRole
        name={state.name}
        onNameChange={nameChangeHandler}
        hours={state.hours}
        onHourChange={hourChangeHandler}
        onHourAdd={hourAddHandler}
        onHourRemove={hourRemoveHandler}
        currentRoleType={state.roleType}
        roleTypes={data?.shiftRoleTypeFindAll}
        onRoleTypeChange={roleTypeChangeHandler}
        halfHour={state.halfHour}
        onHalfHourChange={halHourChangeHandler}
      />
      <Actions
        actions={[
          {
            id: 1,
            element: (
              <LoadingButton
                loading={loading}
                onClick={submitHandler}
                key="actionAdd"
                color="primary"
                variant="contained"
              >
                Přidat
              </LoadingButton>
            ),
          },
        ]}
      />
    </OverlayLoadingContainer>
  );
};

export default withSnackbar(AddRoleIndex);
