import { useMutation, useQuery } from '@apollo/react-hooks';
import { Button } from '@material-ui/core';
import DeleteIcon from '@material-ui/icons/Delete';
import EditIcon from '@material-ui/icons/Edit';
import VisibilityIcon from '@material-ui/icons/Visibility';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { useSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React, { useState } from 'react';

import MaterialTable from 'lib/materialTable';
import AddModal from 'pages/shiftWeekTemplates/index/addModal';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import withPage from 'components/withPage';

import shiftWeekTemplatesBreadcrumbs from './breadcrumbs';
import shiftWeekTemplatesResources from './resources';
import {
  AddModalSubmitValues,
  ShiftWeekTemplate as ShiftWeekTemplateT,
  ShiftWeekTemplateCreate,
  ShiftWeekTemplateCreateVars,
  ShiftWeekTemplateEdit,
  ShiftWeekTemplateEditVars,
  ShiftWeekTemplateFindAll,
  ShiftWeekTemplateRemove,
  ShiftWeekTemplateRemoveVars,
} from './types';

const SHIFT_WEEK_TEMPLATE_FIND_ALL = gql`
  {
    shiftWeekTemplateFindAll {
      id
      name
      active
      shiftWeek {
        branch {
          id
          name
        }
      }
    }
    userGetLogged {
      planableBranches {
        id
        name
      }
    }
  }
`;

const SHIFT_WEEK_TEMPLATE_REMOVE = gql`
  mutation($id: Int!) {
    shiftWeekTemplateRemove(id: $id) {
      id
      active
    }
  }
`;

const SHIFT_WEEK_TEMPLATE_CREATE = gql`
  mutation($name: String!, $branchId: Int!) {
    shiftWeekTemplateCreate(name: $name, branchId: $branchId) {
      id
      name
      active
      shiftWeek {
        branch {
          id
          name
        }
      }
    }
  }
`;

const SHIFT_WEEK_TEMPLATE_EDIT = gql`
  mutation($id: Int!, $name: String!, $branchId: Int!) {
    shiftWeekTemplateEdit(id: $id, name: $name, branchId: $branchId) {
      id
      name
      shiftWeek {
        branch {
          id
          name
        }
      }
    }
  }
`;

const Edit = (): JSX.Element => <EditIcon color="primary" />;
const Delete = (): JSX.Element => <DeleteIcon color="secondary" />;
const Visibility = (): JSX.Element => <VisibilityIcon color="primary" />;

const ShiftWeekTemplate: React.FC = () => {
  const router = useRouter();
  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState({
    id: null,
    name: null,
    branchId: null,
  });
  const { enqueueSnackbar } = useSnackbar();
  const { data, refetch, loading } = useQuery<ShiftWeekTemplateFindAll>(
    SHIFT_WEEK_TEMPLATE_FIND_ALL,
    {
      fetchPolicy: 'no-cache',
    },
  );
  const [shiftWeekTemplateRemove] = useMutation<
    ShiftWeekTemplateRemove,
    ShiftWeekTemplateRemoveVars
  >(SHIFT_WEEK_TEMPLATE_REMOVE);
  const [shiftWeekTemplateCreate, { loading: createLoading }] = useMutation<
    ShiftWeekTemplateCreate,
    ShiftWeekTemplateCreateVars
  >(SHIFT_WEEK_TEMPLATE_CREATE);
  const [shiftWeekTemplateEdit] = useMutation<
    ShiftWeekTemplateEdit,
    ShiftWeekTemplateEditVars
  >(SHIFT_WEEK_TEMPLATE_EDIT);

  const canAdd = useResources([resources.shiftWeekTemplates.add]);
  const canDelete = useResources([resources.shiftWeekTemplates.delete]);
  const canEdit = useResources([resources.shiftWeekTemplates.edit]);

  const addHandler = (values: AddModalSubmitValues): void => {
    shiftWeekTemplateCreate({
      variables: { name: values.name, branchId: values.branchId },
    })
      .then(() => {
        enqueueSnackbar('Šablona úspěšně vytvořena', { variant: 'success' });
        refetch();
        setAdding(false);
      })
      .catch(() => {
        enqueueSnackbar('Šablonu se nepovedlo vytvořit', { variant: 'error' });
      });
  };

  const editHandler = (values: AddModalSubmitValues): void => {
    shiftWeekTemplateEdit({
      variables: {
        id: editing.id,
        name: values.name,
        branchId: values.branchId,
      },
    })
      .then(() => {
        enqueueSnackbar('Šablona úspěšně upravena', { variant: 'success' });
        refetch();
        setEditing({ id: null, name: null, branchId: null });
      })
      .catch(() => {
        enqueueSnackbar('Šablonu se nepovedlo upravit', { variant: 'error' });
      });
  };

  const modalHandler = (values: AddModalSubmitValues): void => {
    if (editing.name) editHandler(values);
    else addHandler(values);
  };

  return (
    <Paper
      title="Šablony"
      actions={[
        <Button
          key="addAction"
          color="primary"
          variant="contained"
          onClick={() => setAdding(true)}
          disabled={!canAdd}
        >
          Přidat
        </Button>,
      ]}
    >
      <MaterialTable
        columns={[
          { title: 'Název', field: 'name' },
          { title: 'Pobočka', field: 'shiftWeek.branch.name' },
        ]}
        data={data?.shiftWeekTemplateFindAll.filter(t => t.active)}
        actions={[
          canDelete && {
            icon: Delete,
            tooltip: 'Odstranit',
            onClick: (e, row: ShiftWeekTemplateT) => {
              shiftWeekTemplateRemove({ variables: { id: row.id } })
                .then(() => {
                  enqueueSnackbar('Šablona úspešně odstraněna', {
                    variant: 'success',
                  });
                  refetch();
                })
                .catch(() => {
                  enqueueSnackbar('Nepovedlo se odstranit šablonu', {
                    variant: 'error',
                  });
                });
            },
          },
          canEdit && {
            icon: Edit,
            tooltip: 'Upravit',
            onClick: (e, row: ShiftWeekTemplateT) => {
              setEditing({
                id: row.id,
                name: row.name,
                branchId: row.shiftWeek.branch.id,
              });
            },
          },
          canEdit && {
            icon: Visibility,
            tooltip: 'Směny',
            onClick: (e, row: ShiftWeekTemplateT) => {
              router.push({
                pathname: routes.shiftWeekTemplates.week,
                query: { id: row.id },
              });
            },
          },
        ]}
        options={{ filtering: true }}
        isLoading={loading}
      />
      {(adding || editing.name) && (
        <AddModal
          open={adding || editing.name}
          close={() => {
            setAdding(false);
            setEditing({ id: null, name: null, branchId: null });
          }}
          onSubmit={modalHandler}
          userBranches={data?.userGetLogged.planableBranches}
          loading={createLoading}
          defaultName={editing.name}
          defaultBranch={editing.branchId}
          editing={editing.name}
        />
      )}
    </Paper>
  );
};

export default withPage(
  ShiftWeekTemplate,
  shiftWeekTemplatesBreadcrumbs,
  shiftWeekTemplatesResources,
);
