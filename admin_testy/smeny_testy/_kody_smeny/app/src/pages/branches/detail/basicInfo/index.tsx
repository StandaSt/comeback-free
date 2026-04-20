import { useMutation } from '@apollo/react-hooks';
import { TextField } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { withSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useEffect, useState } from 'react';

import Actions from 'components/Actions';
import LoadingButton from 'components/LoadingButton';
import useResources from 'components/resources/useResources';
import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';

import {
  BasicInfoFormTypes,
  BasicInfoProps,
  BranchEdit,
  BranchEditVars,
} from '../types';
import ColorDot from '../../../../components/ColorDot';
import ColorPicker from '../../../../components/ColorPicker';

const BRANCH_EDIT = gql`
  mutation($id: Int!, $name: String!, $color: String!) {
    branchEdit(id: $id, name: $name, color: $color) {
      id
      name
      color
    }
  }
`;

const Index = (props: BasicInfoProps) => {
  const [editing, setEditing] = useState(false);
  const [branchEdit, { loading }] = useMutation<BranchEdit, BranchEditVars>(
    BRANCH_EDIT,
  );
  const { handleSubmit, register, errors, reset } = useForm<
    BasicInfoFormTypes
  >();
  const [color, setColor] = useState(props.color);

  useEffect(() => {
    setColor(props.color);
  }, [props.color]);

  const canEdit = useResources([resources.branches.edit]);

  const submitHandler = (values: BasicInfoFormTypes) => {
    branchEdit({ variables: { id: props.id, name: values.name, color } })
      .then(() => {
        props.enqueueSnackbar('Pobočka úspěšně upravena', {
          variant: 'success',
        });
        setEditing(false);
      })
      .catch(() => {
        props.enqueueSnackbar('Pobočku se nepovedlo upravit', {
          variant: 'error',
        });
      });
  };

  return (
    <>
      <SimpleTable>
        <SimpleRow name="Název">
          {!editing ? (
            props.name
          ) : (
            <TextField
              name="name"
              error={errors.name !== undefined}
              defaultValue={props.name}
              inputRef={register({ required: true })}
            />
          )}
        </SimpleRow>
        <SimpleRow name="Barva">
          {!editing ? (
            <ColorDot color={props.color} />
          ) : (
            <ColorPicker value={color} onChange={setColor} />
          )}
        </SimpleRow>
        <SimpleRow name="Status">
          {props.active ? 'Aktivní' : 'Neaktivní'}
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
                  id: 0,
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
                  id: 1,
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

export default withSnackbar(Index);
