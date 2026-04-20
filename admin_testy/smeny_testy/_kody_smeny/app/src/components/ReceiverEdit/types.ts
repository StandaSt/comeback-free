export interface SelectValues {
  resourceId?: number;
  roleId?: number;
}

export interface ReceiverEditProps {
  title: string;
  onSelect: (values: SelectValues) => void;
  loading?: boolean;
  defaultValues?: SelectValues;
}

export interface PanelProps {
  onSelect: (id: number) => void;
  defaultValue?: number;
}
