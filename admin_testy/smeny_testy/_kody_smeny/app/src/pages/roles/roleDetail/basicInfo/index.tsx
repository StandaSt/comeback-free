import { useMutation } from '@apollo/react-hooks';
import { Checkbox, TextField } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import resources from '@shift-planner/shared/config/api/resources';
import { roleNameRegex } from '@shift-planner/shared/config/regexs';
import React, { useState } from 'react';

import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';
import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';

import { BasicInfoProps } from '../types';

import { FormTypes, RoleEdit, RoleEditVars } from './types';

const ROLE_EDIT = gql`
  mutation(
    $roleId: Int!
    $name: String!
    $maxUsers: Int!
    $registrationDefault: Boolean!
    $sortIndex: Int!
  ) {
    roleEdit(
      roleId: $roleId
      name: $name
      maxUsers: $maxUsers
      registrationDefault: $registrationDefault
      sortIndex: $sortIndex
    ) {
      id
      name
      maxUsers
      registrationDefault
      sortIndex
    }
  }
`;

const BasicInfo: React.FC<BasicInfoProps> = props => {
  const [editing, setEditing] = useState(false);
  const [roleEdit, { loading }] = useMutation<RoleEdit, RoleEditVars>(
    ROLE_EDIT,
  );
  const [registrationDefault, setRegistrationDefault] = useState({
    fetched: false,
    value: false,
  });
  const { handleSubmit, register, errors, reset } = useForm<FormTypes>();
  const canEdit = useResources([resources.roles.editRoles]);

  const submitHandler = (values: FormTypes): void => {
    roleEdit({
      variables: {
        roleId: props.role.id,
        name: values.name,
        maxUsers: +values.maxUsers,
        registrationDefault: registrationDefault.value,
        sortIndex: +values.sortIndex,
      },
    })
      .then(res => {
        if (res.data) {
          props.enqueueSnackbar('Role úspešně změněna', { variant: 'success' });
          setEditing(false);
        }
      })
      .catch(() => {
        props.enqueueSnackbar('Role se nepovedlo změnit', { variant: 'error' });
      });
  };

  if (props.role && !registrationDefault.fetched) {
    setRegistrationDefault({
      fetched: true,
      value: props.role.registrationDefault,
    });
  }

  return (
    <>
      <SimpleTable>
        <SimpleRow name="Název">
          {!editing ? (
            props.role?.name
          ) : (
            <TextField
              name="name"
              error={errors.name !== undefined}
              inputRef={register({ required: true, pattern: roleNameRegex })}
              defaultValue={props.role?.name}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Maximílní počet uživatelů">
          {!editing ? (
            props.role?.maxUsers
          ) : (
            <TextField
              name="maxUsers"
              inputRef={register({ required: true })}
              type="number"
              error={errors.maxUsers !== undefined}
              defaultValue={props.role?.maxUsers}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Počet uživatelů">{props.role?.userCount}</SimpleRow>
        <SimpleRow name="Pořadí (zleva)">
          {!editing ? (
            props.role?.sortIndex
          ) : (
            <TextField
              name="sortIndex"
              inputRef={register({ required: true })}
              type="number"
              error={errors.sortIndex !== undefined}
              defaultValue={props.role?.sortIndex}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Výchozí po registraci">
          <Checkbox
            inputRef={register()}
            checked={registrationDefault.value}
            onChange={e =>
              setRegistrationDefault(s => ({ ...s, value: e.target.checked }))
            }
            disabled={!editing}
          />
        </SimpleRow>
      </SimpleTable>
      <Actions
        actions={
          !editing
            ? [
                {
                  id: 1,
                  element: (
                    <LoadingButton
                      loading={props.loading}
                      color="primary"
                      variant="contained"
                      disabled={!canEdit}
                      onClick={() => setEditing(true)}
                    >
                      Upravit
                    </LoadingButton>
                  ),
                },
              ]
            : [
                {
                  id: 1,
                  element: (
                    <LoadingButton
                      loading={loading}
                      color="primary"
                      variant="contained"
                      onClick={handleSubmit(submitHandler)}
                    >
                      Uložit
                    </LoadingButton>
                  ),
                },
                {
                  id: 2,
                  element: (
                    <LoadingButton
                      loading={loading}
                      color="secondary"
                      variant="contained"
                      onClick={() => {
                        reset();
                        setEditing(false);
                      }}
                    >
                      Zrušit
                    </LoadingButton>
                  ),
                },
              ]
        }
      />
    </>
  );
};

export default withSnackbar(BasicInfo);
