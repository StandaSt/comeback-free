import { WithSnackbarProps } from 'notistack';

interface PreferredHour {
  id?: number;
  startHour: number;
  visible: boolean;
}
export interface PreferredDay {
  id: number;
  day: string;
  preferredHours: PreferredHour[];
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
}

export interface PreferredWeekFindByIdVars {
  id: number;
}

export interface PreferredDayChangeHours {
  preferredDayChangeHours: PreferredDay[];
}

export interface DayHours {
  dayId: number;
  hours: number[];
}

export interface PreferredDayChangeHoursVars {
  dayHours: DayHours[];
}

export interface DayT {
  id: number;
  start: string;
  end: string;
}

export interface DayProps {
  name: string;
  start: string;
  end: string;
  onStartChange: (start: string) => void;
  onEndChange: (end: string) => void;
  error: boolean;
}

export type WeekProps = WithSnackbarProps;

interface SummaryDay {
  start: string;
  end: string;
  order: number;
  name: string;
}

export interface SummaryProps {
  days: SummaryDay[];
}
