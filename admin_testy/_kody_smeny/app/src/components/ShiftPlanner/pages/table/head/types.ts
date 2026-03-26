export interface StepperProps {}

export interface HeadIndexProps {
  headExtends?: JSX.Element;
  onDayChange: (day: number) => void;
  defaultDay: number;
  dayTitle: string;
  color: string;
}
