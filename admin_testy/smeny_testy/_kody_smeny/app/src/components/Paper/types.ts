import { PaperProps as PaperPrefabProps } from '@material-ui/core';

export interface PaperProps extends PaperPrefabProps {
  title?: string;
  loading?: boolean;
  actions?: JSX.Element[];
  footer?: JSX.Element;
  actionsFullWidth?: boolean;
}
