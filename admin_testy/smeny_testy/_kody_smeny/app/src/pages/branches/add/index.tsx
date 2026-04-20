import { useMutation } from '@apollo/react-hooks';
import { Grid, TextField } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import routes from '@shift-planner/shared/config/app/routes';
import React, { useState } from 'react';

import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import withPage from 'components/withPage';
import ColorPicker from 'components/ColorPicker';

import AddBreadcrumbs from './breadcrumbs';
import addResources from './resources';
import { AddProps, BranchCreate, BranchCreateVars, FormTypes } from './types';

const BRANCH_CREATE = gql`
  mutation($name: String!, $color: String!) {
    branchCreate(name: $name, color: $color) {
      id
      name
      color
    }
  }
`;

const Add = (props: AddProps) => {
  const router = useRouter();
  const { handleSubmit, register, errors } = useForm<FormTypes>();
  const [branchCreate, { loading }] = useMutation<
    BranchCreate,
    BranchCreateVars
  >(BRANCH_CREATE);
  const [color, setColor] = useState('#000000');

  const submitHandler = (values: FormTypes) => {
    branchCreate({ variables: { name: values.name, color } })
      .then(() => {
        props.enqueueSnackbar('Pobočka úspěšně přidána', {
          variant: 'success',
        });
        router.push(routes.branches.index);
      })
      .catch(() => {
        props.enqueueSnackbar('Pobočku se nepovedlo přidat', {
          variant: 'error',
        });
      });
  };

  return (
    <Paper
      title="Přidání pobočky"
      actions={[
        <LoadingButton
          key="addAction"
          color="primary"
          variant="contained"
          onClick={handleSubmit(submitHandler)}
          loading={loading}
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
            error={errors.name !== undefined}
            inputRef={register({ required: true })}
          />
        </Grid>
        <Grid item xs={12}>
          <ColorPicker
            onChange={setColor}
            value={color}
            label="Barva"
            variant="outlined"
          />
        </Grid>
      </Grid>
    </Paper>
  );
};

export default withPage(withSnackbar(Add), AddBreadcrumbs, addResources);
