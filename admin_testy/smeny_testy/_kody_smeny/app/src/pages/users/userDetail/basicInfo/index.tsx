import { useMutation } from '@apollo/react-hooks';
import { Checkbox, TextField } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import resources from '@shift-planner/shared/config/api/resources';
import { emailRegex } from '@shift-planner/shared/config/regexs';
import dateFormat from 'dateformat';
import React, { useState } from 'react';

import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';
import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';

import { BasicInfoProps, FormTypes, UserEdit, UserEditVars } from './types';

const USER_EDIT = gql`
  mutation(
    $id: Int!
    $email: String!
    $name: String!
    $surname: String!
    $hasOwnCar: Boolean!
    $phoneNumber: String!
    $receiveEmails: Boolean!
  ) {
    userEdit(
      id: $id
      email: $email
      name: $name
      surname: $surname
      hasOwnCar: $hasOwnCar
      phoneNumber: $phoneNumber
      receiveEmails: $receiveEmails
    ) {
      id
      name
      surname
      email
      phoneNumber
      hasOwnCar
      receiveEmails
    }
  }
`;

const BasicInfo: React.FC<BasicInfoProps> = ({ user, loading, ...props }) => {
  const [editing, setEditing] = useState(false);
  const { handleSubmit, register, errors, reset } = useForm<FormTypes>();
  const [userEdit, { loading: mutationLoading }] = useMutation<
    UserEdit,
    UserEditVars
  >(USER_EDIT);
  const canEdit = useResources([resources.users.edit]);

  const submitHandler = (values: FormTypes): void => {
    userEdit({
      variables: {
        id: user.id,
        email: values.email,
        name: values.name,
        surname: values.surname,
        hasOwnCar: values.hasOwnCar || false,
        phoneNumber: values.phoneNumber,
        receiveEmails: values.receiveEmails,
      },
    })
      .then(() => {
        props.enqueueSnackbar('Uživatel úspěšně upraven', {
          variant: 'success',
        });
        setEditing(false);
      })
      .catch(() => {
        props.enqueueSnackbar('Nepovedlo se upravit uživatele', {
          variant: 'error',
        });
      });
  };

  const email = user ? user.email : '';
  const name = user ? user.name : '';
  const surname = user ? user.surname : '';
  const phoneNumber = user?.phoneNumber || '';
  const hasOwnCar = user?.hasOwnCar || false;
  const registerDate = new Date(user?.createTime || Date.now());
  const formattedRegisterDate = dateFormat(registerDate, 'dd.mm.yyyy HH:MM:ss');
  const lastLoginDate = new Date(user?.lastLoginTime || Date.now());
  const formattedLastLoginDate = user?.lastLoginTime
    ? dateFormat(lastLoginDate, 'dd.mm.yyyy HH:MM:ss')
    : '-';

  return (
    <>
      <SimpleTable>
        <SimpleRow name="Email">
          {!editing ? (
            email
          ) : (
            <TextField
              name="email"
              inputRef={register({ required: true, pattern: emailRegex })}
              error={errors.email !== undefined}
              defaultValue={email}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Jméno">
          {!editing ? (
            name
          ) : (
            <TextField
              name="name"
              inputRef={register({ required: true })}
              error={errors.name !== undefined}
              defaultValue={name}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Příjmení">
          {!editing ? (
            surname
          ) : (
            <TextField
              name="surname"
              inputRef={register({ required: true })}
              error={errors.surname !== undefined}
              defaultValue={surname}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Telefonní číslo">
          {!editing ? (
            phoneNumber
          ) : (
            <TextField
              name="phoneNumber"
              inputRef={register({ required: true })}
              error={errors.phoneNumber !== undefined}
              defaultValue={phoneNumber}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Rozvozy vlastním autem">
          {/* eslint-disable-next-line no-nested-ternary */}
          {!editing ? (
            hasOwnCar ? (
              'Ano'
            ) : (
              'Ne'
            )
          ) : (
            <Checkbox
              disabled={!editing}
              name="hasOwnCar"
              inputRef={register()}
              defaultChecked={hasOwnCar}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Dostává emaily">
          {
            // eslint-disable-next-line no-nested-ternary
            !editing ? (
              user?.receiveEmails ? (
                'Ano'
              ) : (
                'Ne'
              )
            ) : (
              <Checkbox
                disabled={!editing}
                name="receiveEmails"
                inputRef={register()}
                defaultChecked={user?.receiveEmails}
              />
            )
          }
        </SimpleRow>
        <SimpleRow name="Datum registrace">{formattedRegisterDate}</SimpleRow>
        <SimpleRow name="Poslední přihlášení">
          {formattedLastLoginDate}
        </SimpleRow>
        <SimpleRow name="Status">
          {user?.active ? 'Aktivní' : 'Neaktivní'}
        </SimpleRow>
        <SimpleRow name="Aktivované notifikace">
          {user?.notificationsActivated ? 'Ano' : 'Ne'}
        </SimpleRow>
      </SimpleTable>
      <Actions
        actions={
          !editing
            ? [
                {
                  id: 0,
                  element: (
                    <LoadingButton
                      loading={loading}
                      variant="contained"
                      color="primary"
                      onClick={() => setEditing(true)}
                      disabled={!canEdit}
                    >
                      Upravit
                    </LoadingButton>
                  ),
                },
              ]
            : [
                {
                  id: 0,
                  element: (
                    <LoadingButton
                      loading={mutationLoading}
                      variant="contained"
                      color="primary"
                      onClick={handleSubmit(submitHandler)}
                    >
                      Uložit
                    </LoadingButton>
                  ),
                },
                {
                  id: 1,
                  element: (
                    <LoadingButton
                      loading={mutationLoading}
                      variant="contained"
                      color="secondary"
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
