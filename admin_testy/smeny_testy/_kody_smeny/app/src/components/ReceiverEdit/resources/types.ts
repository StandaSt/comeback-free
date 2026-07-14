export interface ResourcesQuery {
  resourceFindAll: {
    id: number;
    label: string;
    description: string;
    category: {
      id: number;
      label: string;
    };
  }[];
}

export interface Resource {
  id: number;
  label: string;
  description: string;
  category: {
    id: number;
    label: string;
  };
}

export interface ResourcesProps {
  resources: Resource[];
  loading: boolean;
  onSelect: (id: number) => void;
  defaultValue?: number;
}
