interface Resource {
  id: number;
  label: string;
}

export interface RoleFindById {
  roleFindById: {
    id: number;
    resources: Resource[];
  };
}

export interface RoleFindByIdVars {
  id: number;
}

export interface ResourcesProps {
  resources: Resource[];
}
