import React, { useState } from 'react';
import { Button, TextField } from '@material-ui/core';
import { useForm } from 'react-hook-form';
import resources from '@shift-planner/shared/config/api/resources';

import Paper from 'components/Paper';
import SimpleTable from 'components/table/SimpleTable';
import SimpleRow from 'components/table/SimpeRow';
import useResources from 'components/resources/useResources';

import { EventNotificationProps, FormValues } from './types';

const EventNotification: React.FC<EventNotificationProps> = props => {
  const canEdit = useResources([resources.notifications.eventEdit]);

  const [editing, setEditing] = useState(false);
  const { register, handleSubmit } = useForm<FormValues>();

  const submitHandler = (values: FormValues): void => {
    props.onEdit(values);
    setEditing(false);
  };

  return (
    <form onSubmit={handleSubmit(submitHandler)}>
      <Paper
        title={props.notification?.label || ''}
        loading={props.loading}
        actions={
          editing
            ? [
                <Button
                  key={0}
                  color="primary"
                  variant="contained"
                  type="submit"
                >
                  Uložit
                </Button>,
                <Button
                  key={1}
                  color="secondary"
                  variant="contained"
                  onClick={() => setEditing(false)}
                >
                  Zrušit
                </Button>,
              ]
            : [
                <Button
                  key={1}
                  variant="contained"
                  color="primary"
                  disabled={!canEdit}
                  onClick={() => {
                    setEditing(true);
                  }}
                >
                  Upravit
                </Button>,
              ]
        }
      >
        <SimpleTable>
          <SimpleRow name="Popis">
            {props.notification?.description || ''}
          </SimpleRow>
          <SimpleRow name="Zpráva">
            {editing ? (
              <TextField
                name="message"
                label="Zpráva"
                fullWidth
                multiline
                rows={4}
                defaultValue={props.notification?.message}
                inputRef={register({ required: true })}
              />
            ) : (
              props.notification?.message || ''
            )}
          </SimpleRow>
          <SimpleRow name="Proměnné">
            {props.notification?.variables.map(v => (
              <div key={v.value}>{`{${v.value}} - ${v.description}`}</div>
            ))}
          </SimpleRow>
        </SimpleTable>
      </Paper>
    </form>
  );
};

export default EventNotification;
