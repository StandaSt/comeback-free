export interface PreferredDay {
  id: number;
  day: string;
  preferredHours: {
    id: number;
    startHour: number;
    visible: boolean;
  }[];
}

export interface PreferredWeekFindById {
  preferredWeekFindById: {
    id: number;
    startDay: Date;
    preferredDays: PreferredDay[];
  };
  globalSettingsFindDayStart: {
    id: number;
    value: string;
  };
  globalSettingsFindPreferredDeadline: {
    id: number;
    value: string;
  };
}

export interface PreferredWeekFindByIdVars {
  id: number;
}
