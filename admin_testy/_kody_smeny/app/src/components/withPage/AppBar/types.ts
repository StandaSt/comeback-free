import { User } from 'components/withPage/types';

export interface AppBarProps {
  drawerOpen: () => void;
  onLogOut: () => void;
  user: User;
}
