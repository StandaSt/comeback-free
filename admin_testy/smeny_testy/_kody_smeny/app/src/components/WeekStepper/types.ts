export interface WeekStepperProps {
  onDayChange: (day: number) => void;
  buttonsDisabled?: boolean;
  defaultDay?: number;
  color?: string;
  center?: boolean;
  left?: boolean;
}
