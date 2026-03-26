export interface ShiftWeekSummaryPropsIndex {
  skipWeeks: number;
  title: string;
}

export interface ShiftDay {
  id: number;
  day: string;
  shiftRoles: {
    id: number;
    halfHour: boolean;
    type: {
      id: number;
      name: string;
    };
    shiftHours: {
      id: number;
      startHour: number;
      confirmed: boolean;
      isFirst: boolean;
      employee: {
        id: number;
        name: string;
        surname: string;
      };
    }[];
  }[];
}

interface Branch {
  id: number;
  branch: {
    id: number;
    name: string;
  };
  published: boolean;
  shiftDays: ShiftDay[];
}

interface PlanableBranch {
  id: number;
  name: string;
}

export interface BranchGetShiftWeeks {
  branchGetShiftWeek: Branch;
}

export interface BranchGetShiftWeeksVars {
  branchId: number;
  skipWeeks: number;
}

export interface GlobalData {
  userGetLogged: {
    planableBranches: PlanableBranch[];
  };
  globalSettingsFindDayStart: {
    id: number;
    value: string;
  };
  shiftWeekGetStartDay: number;
}

export interface GlobalDataVariables {
  skipWeeks: number;
}

export interface ShiftWeekSummaryProps {
  users: Map<number, UserMapValue>;
  dayStart: number;
}

export interface CsvDownloadProps {
  users: Map<number, UserMapValue>;
  dayStart: number;
  disabled: boolean;
  branchName: string;
}

export interface BranchSelectProps {
  branches: PlanableBranch[];
  selected: number | string;
  onChange: (branchId: number) => void;
}

export interface MapHour {
  startHour: number;
  shiftRoleType: string;
  halfHour: boolean;
}

export interface UserMapValue {
  name: string;
  surname: string;
  confirmed: boolean;
  monday: MapHour[];
  tuesday: MapHour[];
  wednesday: MapHour[];
  thursday: MapHour[];
  friday: MapHour[];
  saturday: MapHour[];
  sunday: MapHour[];
  shiftRoleTypesIndexes: number[];
}
