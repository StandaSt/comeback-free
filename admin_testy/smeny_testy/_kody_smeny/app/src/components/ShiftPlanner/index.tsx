import React from 'react';

import PageRouter from 'components/PageRouter';
import AssignWorker from 'components/ShiftPlanner/pages/assignWorker';

import AddRole from './pages/addRole';
import EditRole from './pages/editRole';
import TableIndex from './pages/table';
import shiftPlannerRoutes from './routes';
import { ShiftPlannerIndexProps } from './types';

const ShiftPlannerIndex = (props: ShiftPlannerIndexProps) => {
  return (
    <PageRouter
      onPageChange={(page: string) => {
        let title = '';
        switch (page) {
          case shiftPlannerRoutes.table:
            title = '';
            break;
          case shiftPlannerRoutes.addRole:
            title = 'přidání slotu';
            break;
          case shiftPlannerRoutes.editRole:
            title = 'úprava slotu';
            break;
          case shiftPlannerRoutes.assignWorker:
            title = 'přiřazení';
            break;
          default:
            break;
        }
        props.onTitleChange(title);
      }}
      pages={[
        {
          name: shiftPlannerRoutes.table,
          component: TableIndex,
          default: true,
          props: {
            shiftWeek: props.shiftWeek,
            headExtends: props.headExtends,
            disabledAssigning: props.disabledAssigning,
            disabledRoles: props.disabledRoles,
            defaultDay: props.defaultDay,
          },
        },
        {
          name: shiftPlannerRoutes.addRole,
          component: AddRole,
          props: { refetchWeek: props.refetchWeek, hideDate: props.hideDate },
        },
        {
          name: shiftPlannerRoutes.editRole,
          component: EditRole,
          props: { refetchWeek: props.refetchWeek, hideDate: props.hideDate },
        },
        {
          name: shiftPlannerRoutes.assignWorker,
          component: AssignWorker,
          disabled: props.disabledAssigning,
        },
      ]}
    />
  );
};

export default ShiftPlannerIndex;
