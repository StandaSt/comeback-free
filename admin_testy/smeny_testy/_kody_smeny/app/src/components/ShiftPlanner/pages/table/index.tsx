import { useQuery } from '@apollo/react-hooks';
import { Box } from '@material-ui/core';
import { gql } from 'apollo-boost';
import React, { useState } from 'react';
import dateFormat from 'dateformat';

import { translatedDayList } from 'components/dayList';
import shiftPlannerRoutes from 'components/ShiftPlanner/routes';

import HeadIndex from './head';
import Table from './table';
import { GlobalSettingsFindDayStart, TableIndexProps } from './types';

const GLOBAL_SETTINGS_FIND_DAY_START = gql`
  {
    globalSettingsFindDayStart {
      id
      value
    }
    userGetLogged {
      planableShiftRoleTypes {
        id
      }
    }
  }
`;

const TableIndex: React.FC<TableIndexProps> = props => {
  const { data } = useQuery<GlobalSettingsFindDayStart>(
    GLOBAL_SETTINGS_FIND_DAY_START,
    { fetchPolicy: 'no-cache' },
  );

  const [day, setDay] = useState(
    +props.query?.dayIndex || props.defaultDay || 0,
  );
  const days = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
  ];

  let dayDateFormatted = null;
  if (props.shiftWeek?.startDay) {
    const dayDate = new Date(props.shiftWeek?.startDay);
    dayDate.setDate(dayDate.getDate() + day);
    dayDateFormatted = dateFormat(dayDate, 'd. m.');
  }

  const upperTranslatedDay =
    translatedDayList[day]?.charAt(0).toUpperCase() +
    translatedDayList[day]?.slice(1);

  const dayTitle = `${
    dayDateFormatted ? `${dayDateFormatted} - ` : ''
  }${upperTranslatedDay}`;

  const shiftDay =
    props.shiftWeek && props.shiftWeek.shiftDays.find(d => d.day === days[day]);

  const redirectToAddRole = () => {
    if (!props.disabledRoles)
      props.redirect(shiftPlannerRoutes.addRole, {
        shiftDayId: shiftDay.id,
        dayIndex: day,
      });
  };

  const redirectToEditRole = (id: number) => {
    if (!props.disabledRoles)
      props.redirect(shiftPlannerRoutes.editRole, {
        roleId: id,
        dayIndex: day,
      });
  };

  const redirectToAssignWorker = (shiftRoleId: number, startHour: number) => {
    if (!props.disabledAssigning)
      props.redirect(shiftPlannerRoutes.assignWorker, {
        shiftRoleId,
        startHour,
        dayIndex: day,
      });
  };
  const color = props.shiftWeek?.branch.color || '#FFFFFF';

  return (
    <div
      style={{
        backgroundColor: color,
        transition: 'background-color 200ms linear',
      }}
    >
      <HeadIndex
        headExtends={props.headExtends}
        onDayChange={(d: number) => setDay(d)}
        defaultDay={+props.query?.dayIndex || props.defaultDay || 0}
        dayTitle={dayTitle}
        color={color}
      />
      <Box padding={1}>
        <Table
          onRoleAdd={redirectToAddRole}
          onRoleEdit={redirectToEditRole}
          shiftDay={shiftDay}
          dayStart={+data?.globalSettingsFindDayStart.value || 0}
          onAssignClick={redirectToAssignWorker}
          disabledRoles={props.disabledRoles}
          disabledAssigning={props.disabledAssigning}
          planableShiftRoleTypes={
            data?.userGetLogged.planableShiftRoleTypes.map(t => t.id) || []
          }
        />
      </Box>
    </div>
  );
};

export default TableIndex;
