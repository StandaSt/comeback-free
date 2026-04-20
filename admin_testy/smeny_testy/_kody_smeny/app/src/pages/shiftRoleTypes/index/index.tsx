import { useMutation, useQuery } from '@apollo/react-hooks';
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Theme,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import DeleteIcon from '@material-ui/icons/Delete';
import EditIcon from '@material-ui/icons/Edit';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React, { useState } from 'react';

import MaterialTable from 'lib/materialTable';
import shiftRoleTypesResources from 'pages/shiftRoleTypes/index/resources';
import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import withPage from 'components/withPage';

import shiftRoleTypesBreadcrumbs from './breadcrumbs';
import {
  ShiftRoleType,
  ShiftRoleTypeDeactivate,
  ShiftRoleTypeDeactivateVars,
  ShiftRoleTypeFindAll,
  ShiftRoleTypeIndexProps,
} from './types';

const SHIFT_ROLE_TYPE_FIND_ALL = gql`
  {
    shiftRoleTypeFindAll {
      id
      name
      registrationDefault
      sortIndex
      color
      useCars
    }
  }
`;

const SHIFT_ROLE_TYPE_DEACTIVATE = gql`
  mutation($id: Int!) {
    shiftRoleTypeDeactivate(id: $id) {
      id
      name
    }
  }
`;

const Delete = (): JSX.Element => <DeleteIcon color="secondary" />;
const Edit = (): JSX.Element => <EditIcon color="primary" />;

const useStyles = makeStyles((theme: Theme) => ({
  colorDot: {
    height: theme.spacing(3),
    width: theme.spacing(3),
    borderRadius: '100%',
  },
}));

const ShiftRoleTypeIndex: React.FC<ShiftRoleTypeIndexProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const { data, loading, refetch } = useQuery<ShiftRoleTypeFindAll>(
    SHIFT_ROLE_TYPE_FIND_ALL,
    {
      fetchPolicy: 'no-cache',
    },
  );
  const [shiftRoleTypeDeactivate, { loading: mutationLoading }] = useMutation<
    ShiftRoleTypeDeactivate,
    ShiftRoleTypeDeactivateVars
  >(SHIFT_ROLE_TYPE_DEACTIVATE);
  const [removeModal, setRemoveModal] = useState(null);

  const canAdd = useResources([resources.shiftRoleTypes.add]);
  const canDelete = useResources([resources.shiftRoleTypes.delete]);
  const canEdit = useResources([resources.shiftRoleTypes.edit]);

  const removeClickHandler = (id: number): void => {
    setRemoveModal(id);
  };

  const removeHandler = (id: number): void => {
    shiftRoleTypeDeactivate({ variables: { id } })
      .then(res => {
        if (res.data) {
          refetch();
          props.enqueueSnackbar('Typ slotů úspěšně odstraněn', {
            variant: 'success',
          });
          setRemoveModal(null);
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Typ slotů se nepovedlo odstranit', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <Paper
        title="Typy slotů"
        actions={[
          <Button
            key="actionAdd"
            color="primary"
            variant="contained"
            onClick={() => router.push(routes.shiftRoleTypes.add)}
            disabled={!canAdd}
          >
            Přidat
          </Button>,
        ]}
      >
        <MaterialTable
          isLoading={loading}
          data={data?.shiftRoleTypeFindAll}
          columns={[
            { title: 'Název', field: 'name' },
            { title: 'Pořadí', field: 'sortIndex' },
            {
              title: 'Výchozí po registraci',
              field: 'registrationDefault',
              render: row => (row.registrationDefault ? 'Ano' : 'Ne'),
              lookup: { true: 'Ano', false: 'Ne' },
            },
            {
              title: 'Používá auto',
              field: 'useCars',
              render: row => (row.useCars ? 'Ano' : 'Ne'),
            },
            {
              title: 'Barva',
              // eslint-disable-next-line react/display-name
              render: (row: ShiftRoleType) => (
                <div
                  className={classes.colorDot}
                  style={{ backgroundColor: row.color }}
                />
              ),
            },
          ]}
          options={{ filtering: true }}
          actions={[
            canDelete && {
              tooltip: 'Odstranit',
              icon: Delete,
              onClick: (event, rowData) => {
                removeClickHandler(rowData.id);
              },
            },
            canEdit && {
              tooltip: 'Upravit',
              disabled: !canEdit,
              icon: Edit,
              onClick: (event, rowData) => {
                router.push({
                  pathname: routes.shiftRoleTypes.edit,
                  query: { id: rowData.id },
                });
              },
            },
          ]}
        />

        <Dialog open={removeModal !== null}>
          <DialogTitle>Něco se může rozbít!</DialogTitle>
          <DialogContent>
            <DialogContentText>
              Typ slotů odstraňte pouze v případě, že není jiná cesta
              (přejmenvání?!?!?). Pokud ho odstraníte, uživatelům zůstane tento
              typ slotů přiřazen a budete ho muset manuálně odstranit u každého
              uživatel a každému uživateli přiřadit nový typ.
            </DialogContentText>
          </DialogContent>
          <DialogActions>
            <LoadingButton
              onClick={() => removeHandler(removeModal)}
              loading={mutationLoading}
              color="primary"
            >
              !!!Přesto odstranit!!!
            </LoadingButton>
            <LoadingButton
              onClick={() => setRemoveModal(null)}
              loading={mutationLoading}
              color="secondary"
            >
              Zrušit
            </LoadingButton>
          </DialogActions>
        </Dialog>
      </Paper>
    </>
  );
};

export default withPage(
  withSnackbar(ShiftRoleTypeIndex),
  shiftRoleTypesBreadcrumbs,
  shiftRoleTypesResources,
);
