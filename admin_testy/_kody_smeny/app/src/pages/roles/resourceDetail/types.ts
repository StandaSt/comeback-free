interface Resource {
  id: number;
  label: string;
  name: string;
  description: string;
  category: {
    id: number;
    label: string;
    name: string;
  };
  minimalCount: number;
  requires: {
    id: number;
    label: string;
    name: string;
  }[];
  requiredBy: {
    id: number;
    label: string;
    name: string;
  }[];
  roles: {
    id: number;
    name: string;
  }[];
}

export interface ResourceFindById {
  resourceFindById: Resource;
}

export interface ResourceFindByIdVars {
  id: number;
}

export interface ResourceDetailProps {
  resource: Resource;
}
