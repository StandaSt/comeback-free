import { RouterPageProps } from 'components/PageRouter/types';

export interface RelevantUser {
  id: number;
  name: string;
  surname: string;
  preferredHours: {
    id: number;
    startHour: number;
    notAssigned: boolean;
    assignedToBranch: {
      id: number;
      name: string;
    };
  }[];
  lastPreferredTime?: Date;
  mainBranch: boolean;
  afterDeadline: boolean;
  perfectMatch: boolean;
  totalWeekHours: number;
  totalPreferredHours: number;
  hasOwnCar: string;
  user: {
    id: number;
    totalEvaluationScore: number;
    workingBranches: {
      id: number;
      name: string;
    }[];
  };
}

export interface ShiftHour {
  id: number;
  startHour: number;
  employee: {
    id: number;
    name: string;
    surname: string;
  };
}

interface ShiftRoleWithEmployee {
  id: number;
  halfHour: boolean;
  firstHour: null;
  shiftHours: ShiftHour[];
  type: {
    id: number;
    name: string;
    useCars: boolean;
  };
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
}

export interface RelevantUserFindAllForShiftRole {
  relevantUserFindAllForShiftRole: RelevantUser[];
  shiftRoleFindById: ShiftRoleWithEmployee;
  globalSettingsFindDayStart: {
    id: number;
    value: string;
  };
}

export interface RelevantUserFindAllForShiftRoleVars {
  shiftRoleId: number;
  startHour: number;
  endHour: number;
  withoutPreferredHours: boolean;
}

export type AssignWorkerProps = RouterPageProps<{
  shiftRoleId: string;
  startHour: string;
  dayIndex?: string;
}>;

interface ShiftRole {
  id: number;
  type: {
    id: number;
    name: string;
  };
  shiftHours: {
    id: number;
    startHour: number;
  };
}

export interface ShiftRoleAssignWorker {
  shiftRoleAssignWorker: ShiftRole;
}

export interface ShiftRoleAssignWorkerVars {
  shiftRoleId: number;
  userId: number;
  from: number;
  to: number;
}

export interface ShiftRoleUnassignWorker {
  shiftRoleUnassignWorker: ShiftRole;
}

export interface ShiftRoleUnassignWorkerVars {
  shiftRoleId: number;
  from: number;
  to: number;
}

export interface ForceDialogProps {
  open: boolean;
  onSubmit: () => void;
  loading: boolean;
  onClose: () => void;
}

export interface CurrentWorkersProps {
  shiftRole: ShiftRoleWithEmployee;
  dayStart: number;
}
