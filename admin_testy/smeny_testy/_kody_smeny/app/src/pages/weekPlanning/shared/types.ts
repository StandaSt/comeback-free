import { ShiftDays } from 'components/ShiftPlanner/fragments/types';

export interface UserGetLogged {
  userGetLogged: {
    id: number;
    planableBranches: {
      id: number;
      name: string;
    }[];
  };
}

interface ShiftWeek extends ShiftDays {
  id: number;
  published: boolean;
  startDay: Date;
  branch: {
    id: number;
    color: string;
    planners: {
      id: number;
      name: string;
      surname: string;
      roles: {
        id: number;
        resources: {
          id: number;
          name: string;
        }[];
      }[];
    }[];
  };
  shiftRoleCount: number;
}

export interface BranchGetNextWeek {
  branchGetShiftWeek: ShiftWeek;
}

export interface BranchGetNextWeekVars {
  branchId: number;
  skipWeeks: number;
}

export interface BranchSelectProps {
  selectedBranch: number;
  branches: { id: number; name: string }[];
  onBranchChange: (id: number) => void;
}

export interface ShiftWeekPublish {
  id: number;
  publish: boolean;
}

export interface ShiftWeekPublishVars {
  id: number;
  publish: boolean;
}

export interface ExtendedHeaderProps {
  selectedBranch: number;
  branches: { id: number; name: string }[];
  onBranchChange: (id: number) => void;
  published: boolean;
  actionLoading: boolean;
  publishDisabled: boolean;
  publishHandler: (publish: boolean) => void;
  weekId: number;
  templateDisabled: boolean;
  clearDisabled: boolean;
  planners: string[];
  branchId: number;
  onClear: () => void;
  noShiftRoles: boolean;
  viewers: string[];
  peopleDisabled: boolean;
}

export interface ShiftWeekTemplate {
  id: number;
  name: string;
}

export interface ShiftWeekTemplateFindByBranchId {
  shiftWeekTemplateFindByBranchId: ShiftWeekTemplate[];
}

export interface ShiftWeekTemplateFindByBranchIdVars {
  branchId: number;
}

export interface CopyTemplateModalProps {
  open: boolean;
  weekId: number;
  onClose: () => void;
  branchId: number;
}

interface ShiftWeekFromTemplate extends ShiftDays {
  id: number;
}

export interface ShiftWeekCopyFromTemplate {
  shiftWeekCopyFromTemplate: ShiftWeekFromTemplate;
  shiftRoleCount: number;
}

export interface ShiftWeekCopyFromTemplateVars {
  templateId: number;
  weekId: number;
}

export interface WeekPlanningProps {
  skipWeeks: number;
  title: string;
  backgroundColor?: string;
  defaultDay?: number;
}

export interface ShiftWeekClear extends ShiftDays {
  shiftRoleCount: number;
  id: number;
}

export interface ShiftWeekClearVars {
  id: number;
}

export interface ClearModalProps {
  onClose: () => void;
  onSubmit: () => void;
  open: boolean;
  loading: boolean;
}

export interface PublishModalProps {
  onClose: () => void;
  onSubmit: () => void;
  open: boolean;
  loading: boolean;
  publishing: boolean;
}

export interface PeopleModalProps {
  open: boolean;
  onClose: () => void;
  planners: string[];
  viewers: string[];
}
