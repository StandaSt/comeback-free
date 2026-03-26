import { PaperProps as PaperPrefabProps } from '@material-ui/core';

export interface PaperWithTabsProps extends PaperPrefabProps {
  title?: string;
  loading?: boolean;
  actions?: JSX.Element[];
  footer?: JSX.Element;
  tabs: { label: string; panel: JSX.Element; disabled?: boolean }[];
}
