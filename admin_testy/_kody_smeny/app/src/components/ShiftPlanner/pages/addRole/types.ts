import { WithSnackbarProps } from 'notistack';
import React from 'react';

import { RouterPageProps } from 'components/PageRouter/types';
import {
  Hour,
  ShiftHoursProps,
} from 'components/ShiftPlanner/components/ShiftHours/types';
import { ShiftDay } from 'components/ShiftPlanner/fragments/types';

export interface State {
  name: string;
  hours: Hour[];
  hourId: number;
  roleType: number;
  halfHour: boolean;
}

interface ShiftRoleType {
  id: number;
  name: string;
}

export interface AddRoleProps extends ShiftHoursProps {
  name: string;
  onNameChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  roleTypes: ShiftRoleType[];
  currentRoleType: number;
  onRoleTypeChange: (id: number) => void;
  halfHour: boolean;
  onHalfHourChange: (checked: boolean) => void;
}

export type ShiftDayAddRole = ShiftDay;

export interface ShiftDayAddRoleVars {
  id: number;
  typeId: number;
  hours: { startHour: number }[];
  halfHour: boolean;
}

export interface AddRoleIndexProps
  extends WithSnackbarProps,
    RouterPageProps<{ dayIndex: string; shiftDayId: string }> {
  refetchWeek: () => void;
  hideDate?: boolean;
}

export interface ShiftRoleTypeFindAll {
  shiftRoleTypeFindAll: ShiftRoleType[];
  globalSettingsFindDayStart: {
    id: number;
    value: string;
  };
  shiftDayFindById: {
    id: number;
    day: string;
    shiftWeek: {
      startDay: Date;
      branch: {
        id: number;
        name: string;
      };
    };
  };
}

export interface ShiftRoleTypeFindAllVariables {
  shiftDayId: number;
}
