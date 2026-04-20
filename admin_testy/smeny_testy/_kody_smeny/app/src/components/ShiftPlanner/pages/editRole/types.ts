import { WithSnackbarProps } from 'notistack';

import { RouterPageProps } from 'components/PageRouter/types';
import { ShiftHoursProps } from 'components/ShiftPlanner/components/ShiftHours/types';

interface ShiftRoleType {
  id: number;
  name: string;
}

export interface ShiftRoleFindById {
  shiftRoleFindById: {
    id: number;
    halfHour: boolean;
    type: {
      id: number;
    };
    shiftHours: {
      id: number;
      startHour: number;
    }[];
    shiftDay: {
      id: number;
      day: string;
      shiftWeek: {
        id: number;
        startDay: Date;
        branch: {
          id: number;
          name: string;
        };
      };
    };
  };
  shiftRoleTypeFindAll: ShiftRoleType[];
  globalSettingsFindDayStart: {
    id: number;
    value: string;
  };
}

export interface ShiftRoleFindByIdVars {
  id: number;
}

export interface ShiftRoleChangeHours {
  shiftRoleChangeHours: {
    id: number;
    shiftHours: {
      id: number;
      startHour: number;
    }[];
  };
}

export interface ShiftRoleChangeHoursVars {
  id: number;
  hours: { startHour: number }[];
  typeId: number;
  halfHour: boolean;
}

export interface EditRoleIndexProps
  extends RouterPageProps<{ dayIndex: string; roleId: string }>,
    WithSnackbarProps {
  refetchWeek: () => void;
  hideDate: boolean;
}

export interface EditRoleProps extends ShiftHoursProps {
  roleTypes: ShiftRoleType[];
  currentRoleType: number;
  onRoleTypeChange: (id: number) => void;
  halfHour: boolean;
  onHalfHourChange: (checked: boolean) => void;
}

export interface ShiftRoleRemove {
  shiftRoleRemove: boolean;
}

export interface ShiftRoleRemoveVars {
  id: number;
}
