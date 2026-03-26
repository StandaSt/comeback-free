import { useMutation } from '@apollo/react-hooks';
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
import { withSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import routes from '@shift-planner/shared/config/app/routes';
import React, { useState } from 'react';

import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import withPage from 'components/withPage';

import addBreadcrumbs from './breadcrumbs';
import addResources from './resources';
import {
  AddProps,
  FormTypes,
  ShiftRoleTypeCreate,
  ShiftRoleTypeCreateVars,
} from './types';

const SHIFT_ROLE_TYPE_CREATE = gql`
  mutation(
    $name: String!
    $registrationDefault: Boolean!
    $sortIndex: Int!
    $color: String!
  ) {
    shiftRoleTypeCreate(
      name: $name
      registrationDefault: $registrationDefault
      sortIndex: $sortIndex
      color: $color
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

const Add: React.FC<AddProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const { handleSubmit, register, errors } = useForm<FormTypes>();
  const [color, setColor] = useState('#000000');
  const [shiftRoleTypeCreate, { loading }] = useMutation<
    ShiftRoleTypeCreate,
    ShiftRoleTypeCreateVars
  >(SHIFT_ROLE_TYPE_CREATE);

  const submitHandler = (inputs: FormTypes): void => {
    shiftRoleTypeCreate({
      variables: {
        name: inputs.name,
        registrationDefault: inputs.registrationDefault,
        sortIndex: +inputs.sortIndex,
        color,
      },
    })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Typ slotů úspěšně přidán', {
            variant: 'success',
          });
          router.push(routes.shiftRoleTypes.index);
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Typ slotů se nepodařilo přidat', {
          variant: 'error',
        });
      });
  };

  return (
    <Paper
      title="Přidání typu směny"
      actions={[
        <LoadingButton
          loading={loading}
          key="addAction"
          color="primary"
          variant="contained"
          onClick={handleSubmit(submitHandler)}
        >
          Přidat
        </LoadingButton>,
      ]}
    >
      <Grid container spacing={2}>
        <Grid item xs={12} />
        <Grid item xs={12}>
          <TextField
            variant="outlined"
            label="název"
            name="name"
            inputRef={register({ required: true })}
            error={errors.name !== undefined}
          />
        </Grid>
        <Grid item xs={12}>
          <TextField
            variant="outlined"
            label="pořadí (zleva)"
            name="sortIndex"
            type="number"
            inputRef={register({ required: true })}
            error={errors.sortIndex !== undefined}
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
          <Checkbox name="registrationDefault" inputRef={register()} />
        </Grid>
      </Grid>
    </Paper>
  );
};

export default withPage(withSnackbar(Add), addBreadcrumbs, addResources);
