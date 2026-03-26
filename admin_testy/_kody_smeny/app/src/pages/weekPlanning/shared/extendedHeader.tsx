import { Grid, IconButton, Typography } from '@material-ui/core';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useState } from 'react';
import GroupIcon from '@material-ui/icons/Group';

import BranchSelect from 'pages/weekPlanning/shared/branchSelect';
import ClearModal from 'pages/weekPlanning/shared/clearModal';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';

import CopyTemplateModal from './copyTemplateModal';
import { ExtendedHeaderProps } from './types';
import PublishModal from './publishModal';
import PeopleModal from './PeopleModal';

const ExtendedHeader: React.FC<ExtendedHeaderProps> = props => {
  const [templateModal, setTemplateModal] = useState(false);
  const [clearModal, setClearModal] = useState(false);
  const [publishModal, setPublishModal] = useState(false);
  const [peopleModal, setPeopleModal] = useState(false);

  const canPublish = useResources([resources.weekPlanning.publish]);
  const canClear = useResources([resources.weekPlanning.clear]);

  const status = props.published ? 'Zveřejněno' : 'Upravování';

  return (
    <>
      <Grid container alignItems="center" spacing={2}>
        <Grid item xs={6}>
          <BranchSelect
            selectedBranch={props.selectedBranch}
            branches={props.branches}
            onBranchChange={props.onBranchChange}
          />
        </Grid>
        <Grid item xs={6} container justify="flex-end" spacing={2}>
          <Grid item>
            <LoadingButton
              loading={props.actionLoading}
              disabled={props.templateDisabled || !props.noShiftRoles}
              color="primary"
              variant="contained"
              onClick={() => setTemplateModal(true)}
            >
              Kopírovat ze šablony
            </LoadingButton>
          </Grid>
          <Grid item>
            <LoadingButton
              loading={props.actionLoading}
              disabled={props.clearDisabled || props.noShiftRoles || !canClear}
              color="secondary"
              variant="contained"
              onClick={() => setClearModal(true)}
            >
              Vyprázdnit
            </LoadingButton>
          </Grid>
          <Grid item>
            <LoadingButton
              loading={props.actionLoading}
              disabled={props.publishDisabled || !canPublish}
              color="primary"
              variant="contained"
              onClick={() => setPublishModal(true)}
            >
              {props.published ? 'K úpravě' : 'Zveřejnit'}
            </LoadingButton>
          </Grid>
        </Grid>
        <Grid item xs={8}>
          <IconButton
            onClick={() => {
              setPeopleModal(true);
            }}
            size="small"
            disabled={props.peopleDisabled}
          >
            <GroupIcon />
          </IconButton>
        </Grid>
        <Grid item xs={4} container justify="flex-end" spacing={2}>
          <Typography>{`Status: ${status}`}</Typography>
        </Grid>
      </Grid>

      <CopyTemplateModal
        open={templateModal}
        weekId={props.weekId}
        onClose={() => setTemplateModal(false)}
        branchId={props.branchId}
      />
      <ClearModal
        onClose={() => setClearModal(false)}
        onSubmit={() => {
          props.onClear();
          setClearModal(false);
        }}
        open={clearModal}
        loading={props.actionLoading}
      />
      <PublishModal
        open={publishModal}
        loading={props.actionLoading}
        publishing={!props.published}
        onClose={() => setPublishModal(false)}
        onSubmit={() => {
          props.publishHandler(!props.published);
          setPublishModal(false);
        }}
      />
      <PeopleModal
        open={peopleModal}
        planners={props.planners}
        viewers={props.viewers}
        onClose={() => {
          setPeopleModal(false);
        }}
      />
    </>
  );
};

export default ExtendedHeader;
