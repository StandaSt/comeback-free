export interface SummaryDay {
  start: string;
  end: string;
  order: number;
  name: string;
}

export interface PreferredWeekSummaryProps {
  days: SummaryDay[];
}
