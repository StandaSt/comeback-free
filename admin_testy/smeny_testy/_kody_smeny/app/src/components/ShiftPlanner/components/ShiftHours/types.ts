export interface Hour {
  id: number;
  from: string;
  to: string;
}

export interface ShiftHoursProps {
  hours: Hour[];
  onHourAdd: () => void;
  onHourChange: (id: number, from: string, to: string) => void;
  onHourRemove: (id: number) => void;
}
