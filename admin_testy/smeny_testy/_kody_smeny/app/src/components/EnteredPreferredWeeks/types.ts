export interface PreferredHour {
  id: number;
  startHour: number;
  notAssigned: boolean;
  visible: boolean;
}
export interface PreferredWeek {
  id: number;
  startDay: string;
  lastEditTime: string | null;
  user: {
    id: number;
    name: string;
    surname: string;
    totalEvaluationScore: number;
    workingBranches: {
      id: number;
      name: string;
    }[];
    shiftRoleTypeNames: string[];
  };
  preferredDays: {
    id: number;
    day: string;
    preferredHours: PreferredHour[];
  }[];
}

export interface PreferredWeekWithPercents extends PreferredWeek {
  percents?: number;
  totalPreferredHours?: number;
  totalUsedPreferredHours?: number;
}

export interface Branch {
  id: number;
  name: string;
}

export interface ShiftRoleType {
  id: number;
  name: string;
}

export interface PreferredWeekFindAllInWeek {
  preferredWeekFindAllInWeek: PreferredWeek[];
  globalSettingsFindDayStart: {
    id: string;
    value: number;
  };
  branchFindAll: Branch[];
  shiftRoleTypeFindAll: ShiftRoleType[];
}

export interface PreferredWeekFindAllInWeekVariables {
  skipWeeks: number;
}
export interface EnteredPreferredWeeksProps {
  skipWeeks: number;
  title: string;
}

export interface EnteredPreferredWeeksUIProps {
  preferredWeeks: PreferredWeekWithPercents[];
  dayStart: number;
  loading: boolean;
  branches: Branch[];
  shiftRoleTypes: ShiftRoleType[];
}
