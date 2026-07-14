import { useLazyQuery, useMutation } from '@apollo/react-hooks';
import {
  Checkbox,
  Grid,
  TextField,
  Theme,
  Typography,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import { gql } from 'apollo-boost';
import ColorPicker from 'material-ui-color-picker';
import { useRouter } from 'next/router';
import { withSnackbar, WithSnackbarProps } from 'notistack';
import routes from '@shift-planner/shared/config/app/routes';
import React, { useState } from 'react';

import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import withPage from 'components/withPage';

import editBreadcrumbs from './breadcrumbs';
import editResources from './resources';
import {
  ShiftRoleTypeEdit,
  ShiftRoleTypeEditVars,
  ShiftRoleTypeFindById,
  ShiftRoleTypeFindByIdVars,
} from './types';

const SHIFT_ROLE_TYPE_FIND_BY_ID = gql`
  query($id: Int!) {
    shiftRoleTypeFindById(id: $id) {
      id
      name
      registrationDefault
      sortIndex
      color
      useCars
    }
  }
`;

const SHIFT_ROLE_TYPE_EDIT = gql`
  mutation(
    $id: Int!
    $name: String!
    $registrationDefault: Boolean!
    $sortIndex: Int!
    $color: String!
    $useCars: Boolean!
  ) {
    shiftRoleTypeEdit(
      id: $id
      name: $name
      registrationDefault: $registrationDefault
      sortIndex: $sortIndex
      color: $color
      useCars: $useCars
    ) {
      id
      name
      registrationDefault
      sortIndex
      color
    }
  }
`;

const useStyles = makeStyles((theme: Theme) => ({
  colorDot: {
    height: theme.spacing(4),
    width: theme.spacing(4),
    borderRadius: '100%',
    marginLeft: theme.spacing(2),
  },
}));

const Edit: React.FC<WithSnackbarProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const [shiftRoleTypeFindById, { data, loading, error }] = useLazyQuery<
    ShiftRoleTypeFindById,
    ShiftRoleTypeFindByIdVars
  >(SHIFT_ROLE_TYPE_FIND_BY_ID);
  const [shiftRoleTypeEdit, { loading: mutationLoading }] = useMutation<
    ShiftRoleTypeEdit,
    ShiftRoleTypeEditVars
  >(SHIFT_ROLE_TYPE_EDIT);
  const [updated, setUpdated] = useState(false);
  const [name, setName] = useState('');
  const [sortIndex, setSortIndex] = useState(0);
  const [registrationDefault, setRegistrationDefault] = useState(false);
  const [color, setColor] = useState('#000000');
  const [useCars, setUseCars] = useState(false);

  if (router.query.id && !data && !loading && !error) {
    shiftRoleTypeFindById({ variables: { id: +router.query.id } });
  }

  if (data && !updated) {
    setUpdated(true);
    setName(data.shiftRoleTypeFindById.name);
    setRegistrationDefault(data.shiftRoleTypeFindById.registrationDefault);
    setSortIndex(data.shiftRoleTypeFindById.sortIndex);
    setColor(data.shiftRoleTypeFindById.color);
    setUseCars(data.shiftRoleTypeFindById.useCars);
  }

  const submitHandler = (): void => {
    shiftRoleTypeEdit({
      variables: {
        id: +router.query.id,
        name,
        registrationDefault,
        sortIndex,
        color,
        useCars,
      },
    })
      .then(() => {
        props.enqueueSnackbar('Typ slotů úspěšně upraven', {
          variant: 'success',
        });
        router.push(routes.shiftRoleTypes.index);
      })
      .catch(() => {
        props.enqueueSnackbar('Typ slotů se nepovedlo upravit', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <Paper
        loading={loading}
        title="Editace typu slotů"
        actions={[
          <LoadingButton
            loading={mutationLoading || loading}
            key="actionEdit"
            color="primary"
            variant="contained"
            onClick={submitHandler}
          >
            Uložit
          </LoadingButton>,
        ]}
      >
        <Grid container spacing={2}>
          <Grid item xs={12} />
          <Grid item xs={12}>
            <TextField
              variant="outlined"
              label="název"
              value={name}
              onChange={e => setName(e.target.value)}
            />
          </Grid>
          <Grid item xs={12}>
            <TextField
              variant="outlined"
              label="pořadí (zleva)"
              type="number"
              value={sortIndex}
              onChange={e => setSortIndex(+e.target.value)}
            />
          </Grid>
          <Grid item xs={12} container alignItems="center">
            <ColorPicker
              variant="outlined"
              label="barva"
              defaultValue="#000000"
              value={color}
              onChange={c => setColor(c)}
            />
            <div
              className={classes.colorDot}
              style={{ backgroundColor: color }}
            />
          </Grid>
          <Grid item xs={12} style={{ display: 'flex', alignItems: 'center' }}>
            <Typography>Výchozí po registraci:</Typography>
            <Checkbox
              name="registrationDefault"
              checked={registrationDefault}
              onChange={e => setRegistrationDefault(e.target.checked)}
            />
          </Grid>
          <Grid item xs={12} style={{ display: 'flex', alignItems: 'center' }}>
            <Typography>Používá auto:</Typography>
            <Checkbox
              checked={useCars}
              onChange={e => setUseCars(e.target.checked)}
            />
          </Grid>
        </Grid>
      </Paper>
    </>
  );
};

export default withPage(withSnackbar(Edit), editBreadcrumbs, editResources);
