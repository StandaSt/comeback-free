import { Dialog, DialogActions, DialogTitle } from '@material-ui/core';
import React from 'react';

import { PublishModalProps } from 'pages/weekPlanning/shared/types';
import LoadingButton from 'components/LoadingButton';

const PublishModal: React.FC<PublishModalProps> = props => (
  <Dialog open={props.open}>
    <DialogTitle>
      {props.publishing
        ? 'Doopravdy chcete tento týden zveřejnit?'
        : 'Doopravdy chcete vrátit tento týden k úpravám?'}
    </DialogTitle>
    <DialogActions>
      <LoadingButton
        color="primary"
        variant="text"
        onClick={props.onSubmit}
        loading={props.loading}
      >
        {props.publishing ? 'Zveřejnit' : 'K úpravě'}
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

export default PublishModal;
