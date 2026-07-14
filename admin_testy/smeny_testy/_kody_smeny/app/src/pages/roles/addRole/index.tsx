import { useMutation } from '@apollo/react-hooks';
import { Checkbox, TextField, Theme, Typography } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import { gql } from 'apollo-boost';
import Link from 'next/link';
import { useRouter } from 'next/router';
import { withSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import apiErrors from '@shift-planner/shared/config/api/errors';
import routes from '@shift-planner/shared/config/app/routes';
import { roleNameRegex } from '@shift-planner/shared/config/regexs';
import { Dispatch } from 'redux';
import { connect } from 'react-redux';
import React from 'react';

import addRoleBreadcrumbs from 'pages/roles/addRole/breadcrumbs';
import addRoleResources from 'pages/roles/addRole/resources';
import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import withPage from 'components/withPage';
import { rolesAddRole } from 'redux/actions/roles';
import { Role } from 'redux/reducers/roles/types';

import { AddRoleProps, MapDispatch, RoleCreate, RoleCreateVars } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  textField: {
    paddingTop: theme.spacing(2),
    display: 'flex',
    alignItems: 'center',
  },
}));

const ROLE_CREATE = gql`
  mutation($name: String!, $maxUsers: Int!, $registrationDefault: Boolean!) {
    roleCreate(
      name: $name
      maxUsers: $maxUsers
      registrationDefault: $registrationDefault
    ) {
      id
      name
      resources {
        id
        name
      }
      registrationDefault
    }
  }
`;

const AddRole: React.FC<AddRoleProps> = props => {
  const classes = useStyles();

  const router = useRouter();
  const [roleCreate, { loading }] = useMutation<RoleCreate, RoleCreateVars>(
    ROLE_CREATE,
  );
  const { handleSubmit, register, errors } = useForm<{
    name: string;
    maxUsers: string;
    registrationDefault: boolean;
  }>();

  const onSubmit = values => {
    roleCreate({
      variables: {
        name: values.name,
        maxUsers: +values.maxUsers,
        registrationDefault: values.registrationDefault,
      },
    })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Role úspěšně vytvořena', {
            variant: 'success',
          });
          props.addRole(res.data.roleCreate);
          router.push(routes.roles.index);
        }
      })
      .catch(error => {
        if (
          error.graphQLErrors.some(
            e =>
              typeof e.message === 'string' &&
              e.message.startsWith(apiErrors.db.duplicate),
          )
        ) {
          props.enqueueSnackbar('Role s tímto jménem již existuje', {
            variant: 'warning',
          });
        } else if (
          error.graphQLErrors.some(
            e => e.message.message === apiErrors.input.invalid,
          )
        ) {
          props.enqueueSnackbar('Na server přišel špatný požadavek', {
            variant: 'error',
          });
        } else {
          props.enqueueSnackbar('Něco se pokazilo', { variant: 'error' });
        }
      });
  };

  return (
    <Paper
      title="Přidání role"
      actions={[
        <LoadingButton
          loading={loading}
          key="actionAdd"
          color="primary"
          variant="contained"
          onClick={handleSubmit(onSubmit)}
        >
          Přidat
        </LoadingButton>,
        <Link key="actionCancel" href={routes.roles.index}>
          <LoadingButton
            loading={loading}
            key="actionCancel"
            color="secondary"
            variant="contained"
          >
            Zrušit
          </LoadingButton>
        </Link>,
      ]}
    >
      <form>
        <div className={classes.textField}>
          <TextField
            variant="outlined"
            label="Název role"
            name="name"
            inputRef={register({ required: true, pattern: roleNameRegex })}
            error={errors.name !== undefined}
          />
        </div>
        <div className={classes.textField}>
          <TextField
            variant="outlined"
            type="number"
            label="Maximální počet uživatelů"
            name="maxUsers"
            defaultValue={9999}
            inputRef={register({ required: true })}
            error={errors.name !== undefined}
          />
        </div>
        <div className={classes.textField}>
          <Typography>Výchozí po registraci:</Typography>
          <Checkbox name="registrationDefault" inputRef={register()} />
        </div>
      </form>
    </Paper>
  );
};

const mapDispatchToProps = (dispatch: Dispatch): MapDispatch => ({
  addRole: (role: Role) => dispatch(rolesAddRole(role)),
});

export default withPage(
  connect(undefined, mapDispatchToProps)(withSnackbar(AddRole)),
  addRoleBreadcrumbs,
  addRoleResources,
);
