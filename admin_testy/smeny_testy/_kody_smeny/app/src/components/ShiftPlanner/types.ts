import { ShiftDays } from './fragments/types';

export interface ShiftWeek extends ShiftDays {
  id: number;
  branch: {
    id: number;
    color: string;
  };
}

export interface ShiftPlannerIndexProps {
  shiftWeek: ShiftWeek;
  onTitleChange: (title: string) => void;
  headExtends?: JSX.Element;
  refetchWeek: () => void;
  disabledAssigning?: boolean;
  disabledRoles?: boolean;
  hideDate?: boolean;
  defaultDay?: number;
}
