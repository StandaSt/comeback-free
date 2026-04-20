import { useMutation, useQuery } from '@apollo/react-hooks';
import {
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
} from '@material-ui/core';
import DoneIcon from '@material-ui/icons/Done';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import React from 'react';

import MaterialTable from 'lib/materialTable';
import LoadingButton from 'components/LoadingButton';
import shiftDaysFragment from 'components/ShiftPlanner/fragments/shiftDaysFragment';

import {
  CopyTemplateModalProps,
  ShiftWeekCopyFromTemplate,
  ShiftWeekCopyFromTemplateVars,
  ShiftWeekTemplate,
  ShiftWeekTemplateFindByBranchId,
  ShiftWeekTemplateFindByBranchIdVars,
} from './types';

const SHIFT_WEEK_TEMPLATE_FIND_BY_BRANCH_ID = gql`
  query($branchId: Int!) {
    shiftWeekTemplateFindByBranchId(branchId: $branchId) {
      id
      name
    }
  }
`;

const SHIFT_WEEK_COPY_FROM_TEMPLATE = gql`
  ${shiftDaysFragment}
  mutation($templateId: Int!, $weekId: Int!) {
    shiftWeekCopyFromTemplate(templateId: $templateId, weekId: $weekId) {
      id
      shiftRoleCount
      ...ShiftDays
    }
  }
`;

const Done = (): JSX.Element => <DoneIcon color="primary" />;

const CopyTemplateModal: React.FC<CopyTemplateModalProps> = props => {
  const { data, loading } = useQuery<
    ShiftWeekTemplateFindByBranchId,
    ShiftWeekTemplateFindByBranchIdVars
  >(SHIFT_WEEK_TEMPLATE_FIND_BY_BRANCH_ID, {
    variables: { branchId: props.branchId },
    fetchPolicy: 'no-cache',
  });
  const [shiftWeekCopyFromTemplate, { loading: mutationLoading }] = useMutation<
    ShiftWeekCopyFromTemplate,
    ShiftWeekCopyFromTemplateVars
  >(SHIFT_WEEK_COPY_FROM_TEMPLATE);
  const { enqueueSnackbar } = useSnackbar();

  const selectHandler = (id: number): void => {
    shiftWeekCopyFromTemplate({
      variables: { templateId: id, weekId: props.weekId },
    })
      .then(() => {
        enqueueSnackbar('Šablona úspěšně zkopírována', { variant: 'success' });
        props.onClose();
      })
      .catch(() => {
        enqueueSnackbar('Šablonu se nepovedlo zkopírovat', {
          variant: 'error',
        });
      });
  };

  return (
    <Dialog open={props.open}>
      <DialogTitle>Kopírování ze šablony</DialogTitle>
      <DialogContent>
        <MaterialTable
          columns={[{ title: 'Název', field: 'name' }]}
          data={data?.shiftWeekTemplateFindByBranchId}
          isLoading={loading}
          actions={[
            {
              icon: Done,
              tooltip: 'Vybrat',
              onClick: (e, row: ShiftWeekTemplate) => {
                selectHandler(row.id);
              },
              disabled: mutationLoading,
            },
          ]}
          options={{ filtering: true }}
        />
      </DialogContent>
      <DialogActions>
        <LoadingButton
          color="secondary"
          loading={mutationLoading}
          onClick={props.onClose}
        >
          Zrušit
        </LoadingButton>
      </DialogActions>
    </Dialog>
  );
};

export default CopyTemplateModal;
