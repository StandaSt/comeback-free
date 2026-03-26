export interface RolesQuery {
  roleFindAll: {
    id: number;
    name: string;
  }[];
}

export interface Role {
  id: number;
  name: string;
}

export interface RolesProps {
  roles: Role[];
  loading: boolean;
  onSelect: (id: number) => void;
  defaultValue?: number;
}
