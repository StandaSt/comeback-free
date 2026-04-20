import { RouterPageProps } from 'components/PageRouter/types';
import { ShiftDay, ShiftDays } from 'components/ShiftPlanner/fragments/types';

interface ShiftWeek extends ShiftDays {
  id: number;
  startDay?: Date;
  branch: {
    id: number;
    color: string;
  };
}

export interface TableProps {
  shiftDay: ShiftDay;
  onRoleAdd: () => void;
  onRoleEdit: (id: number) => void;
  dayStart: number;
  onAssignClick: (shiftRoleId: number, startHour: number) => void;
  disabledRoles?: boolean;
  disabledAssigning?: boolean;
  planableShiftRoleTypes: number[];
}

export interface TableIndexProps
  extends RouterPageProps<{ dayIndex?: string }> {
  shiftWeek: ShiftWeek;
  headExtends?: JSX.Element;
  disabledAssigning?: boolean;
  disabledRoles?: boolean;
  defaultDay?: number;
}

export interface GlobalSettingsFindDayStart {
  globalSettingsFindDayStart: {
    id: number;
    value: string;
  };
  userGetLogged: {
    planableShiftRoleTypes: {
      id: number;
    }[];
  };
}
