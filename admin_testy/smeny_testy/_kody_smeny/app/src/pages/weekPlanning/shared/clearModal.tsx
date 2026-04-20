import { Dialog, DialogActions, DialogTitle } from '@material-ui/core';
import React from 'react';

import { ClearModalProps } from 'pages/weekPlanning/shared/types';
import LoadingButton from 'components/LoadingButton';

const ClearModal: React.FC<ClearModalProps> = props => (
  <Dialog open={props.open}>
    <DialogTitle>Doopravdy chcete týden vyprázdnit?</DialogTitle>
    <DialogActions>
      <LoadingButton
        color="primary"
        variant="text"
        onClick={props.onSubmit}
        loading={props.loading}
      >
        Vyprázdnit
      </LoadingButton>
      <LoadingButton
        color="secondary"
        variant="text"
        onClick={props.onClose}
        loading={props.loading}
      >
        Zrušit
      </LoadingButton>
    </DialogActions>
  </Dialog>
);

export default ClearModal;
