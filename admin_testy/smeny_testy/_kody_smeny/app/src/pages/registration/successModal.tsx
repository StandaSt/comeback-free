import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@material-ui/core';
import Link from 'next/link';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import { SuccessModalProps } from './types';

const SuccessModal = (props: SuccessModalProps) => (
  <Dialog open={props.open}>
    <DialogTitle>Registrace úspěšná</DialogTitle>
    <DialogContent>
      <DialogContentText>
        Vyčkejte na aktivaci účtu administrátorem. O právě uvedené registraci
        byl informován.
      </DialogContentText>
    </DialogContent>
    <DialogActions>
      <Link href={routes.login}>
        <Button color="primary">Beru na vědomí</Button>
      </Link>
    </DialogActions>
  </Dialog>
);

export default SuccessModal;
