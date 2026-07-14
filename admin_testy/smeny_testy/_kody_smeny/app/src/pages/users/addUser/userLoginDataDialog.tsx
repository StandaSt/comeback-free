import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Typography,
} from '@material-ui/core';
import React from 'react';

import { UserLoginDataModalProps } from './types';

const UserLoginDataDialog: React.FC<UserLoginDataModalProps> = props => (
  <Dialog open={props.open}>
    <DialogTitle>Přihlašovací údaje uživatele</DialogTitle>
    <DialogContent>
      <Typography>{`Email: ${props.email}`}</Typography>
      <Typography>{`Heslo: ${props.password}`}</Typography>
    </DialogContent>
    <DialogActions>
      <Button color="primary" onClick={props.close}>
        Zavřít
      </Button>
    </DialogActions>
  </Dialog>
);

export default UserLoginDataDialog;
