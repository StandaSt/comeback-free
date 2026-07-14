export interface WorkingWeekProps {
  skipWeeks: number;
  title: string;
  backgroundColor?: string;
}

export interface WorkingInterval {
  from: number;
  to: number;
  halfHour: boolean;
  branchName: string;
  shiftRoleType: string;
}

interface Day {
  workingIntervals: WorkingInterval[];
}

export interface WorkingWeekGetFromCurrentWeek {
  workingWeekGetFromCurrentWeek: {
    totalBranchCount: number;
    publishedBranches: string[];
    monday: Day;
    tuesday: Day;
    wednesday: Day;
    thursday: Day;
    friday: Day;
    saturday: Day;
    sunday: Day;
    preferredWeek: {
      id: number;
      confirmed: boolean;
    };
  };
  shiftWeekGetStartDay: string;
}

export interface WorkingWeekGetFromCurrentWeekVars {
  skipWeeks: number;
}

export interface PreferredWeekConfirm {
  preferredWeekConfirm: {
    id: number;
    confirmed: boolean;
  };
}

export interface PreferredWeekConfirmVariables {
  weekId: number;
}
