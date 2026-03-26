import React from 'react';
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  Typography,
} from '@material-ui/core';

import { PeopleModalProps } from './types';

const PeopleModal: React.FC<PeopleModalProps> = props => {
  return (
    <Dialog open={props.open}>
      <DialogContent>
        <Typography variant="h6">Vidí</Typography>
        <Typography>{props.viewers.join(', ')}</Typography>
        <Typography variant="h6">Plánují</Typography>
        <Typography>{props.planners.join(', ')}</Typography>
      </DialogContent>
      <DialogActions>
        <Button color="primary" onClick={props.onClose}>
          Zavřit
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default PeopleModal;
