export interface ShiftWeekTemplate {
  id: number;
  name: string;
  active: boolean;
  shiftWeek: {
    branch: {
      id: number;
      name: string;
    };
  };
}

interface PlanableBranch {
  id: number;
  name: string;
}

export interface ShiftWeekTemplateFindAll {
  shiftWeekTemplateFindAll: ShiftWeekTemplate[];
  userGetLogged: {
    planableBranches: PlanableBranch[];
  };
}

export interface ShiftWeekTemplateRemove {
  shiftWeekTemplateRemove: {
    id: number;
    active: boolean;
  };
}

export interface ShiftWeekTemplateCreate {
  shiftWeekTemplateCreate: {
    id: number;
    name: string;
    active: boolean;
    shiftWeek: {
      branch: {
        id: number;
        name: string;
      };
    };
  };
}
export interface ShiftWeekTemplateCreateVars {
  name: string;
  branchId: number;
}

export interface ShiftWeekTemplateRemoveVars {
  id: number;
}

export interface ShiftWeekTemplateEdit {
  shiftWeekTemplateRename: {
    id: number;
    name: string;
    shiftWeek: {
      branch: {
        id: number;
        name: string;
      };
    };
  };
}

export interface ShiftWeekTemplateEditVars {
  id: number;
  name: string;
  branchId: number;
}

export interface AddModalFormValues {
  name: string;
}

export interface AddModalSubmitValues extends AddModalFormValues {
  branchId: number;
}

export interface AddModalProps {
  open: boolean;
  userBranches: PlanableBranch[];
  close: () => void;
  onSubmit: (values: AddModalSubmitValues) => void;
  loading: boolean;
  defaultName?: string;
  defaultBranch?: number;
  editing?: boolean;
}
