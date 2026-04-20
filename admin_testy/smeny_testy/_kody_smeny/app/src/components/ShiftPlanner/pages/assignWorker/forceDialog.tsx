import {
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@material-ui/core';
import React from 'react';

import LoadingButton from 'components/LoadingButton';
import { ForceDialogProps } from 'components/ShiftPlanner/pages/assignWorker/types';

const ForceDialog = (props: ForceDialogProps) => (
  <Dialog open={props.open}>
    <DialogTitle>Uživatel nemá úplnou shodu požadavků</DialogTitle>
    <DialogContent>
      <DialogContentText>
        Tento časový interval se neshoduje s požadavky uživatele. Ověřte si že
        uživatel má v tento časový interval volný rozvrh.
      </DialogContentText>
    </DialogContent>
    <DialogActions>
      <LoadingButton
        loading={props.loading}
        color="primary"
        onClick={props.onSubmit}
      >
        Přiřadit
      </LoadingButton>
      <LoadingButton
        loading={props.loading}
        color="secondary"
        onClick={props.onClose}
      >
        Zrušit
      </LoadingButton>
    </DialogActions>
  </Dialog>
);

export default ForceDialog;
