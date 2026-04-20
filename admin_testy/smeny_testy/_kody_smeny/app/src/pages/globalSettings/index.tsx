import { useMutation, useQuery } from '@apollo/react-hooks';
import { Button, TextField } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import { useForm } from 'react-hook-form';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useState } from 'react';

import PreferredDeadline from 'pages/globalSettings/preferredDeadline';
import LoadingButton from 'components/LoadingButton';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';
import withPage from 'components/withPage';

import globalSettingsBreadcrumbs from './breadcrumbs';
import globalSettingsResources from './resources';
import {
  FormTypes,
  GlobalSettings,
  GlobalSettingsChange,
  GlobalSettingsChangeVars,
} from './types';

const GLOBAL_SETTINGS = gql`
  {
    globalSettingsFindDayStart {
      id
      value
    }
    globalSettingsFindPreferredWeeksAhead {
      id
      value
    }
    globalSettingsFindPreferredDeadline {
      id
      value
    }
    globalSettingsFindEvaluationCooldown {
      id
      value
    }
    globalSettingsFindEvaluationTTL {
      id
      value
    }
    globalSettingsDeadlineNotification {
      id
      value
    }
  }
`;

const GLOBAL_SETTINGS_CHANGE = gql`
  mutation(
    $dayStart: Int!
    $preferredWeeksAhead: Int!
    $preferredDeadline: DateTime!
    $cooldown: Int!
    $ttl: Int!
    $deadlineNotification: Int!
  ) {
    globalSettingsChangeDayStart(dayStart: $dayStart) {
      id
      value
    }
    globalSettingsChangePreferredWeeksAhead(
      preferredWeeksAhead: $preferredWeeksAhead
    ) {
      id
      value
    }
    globalSettingsChangePreferredDeadline(
      preferredDeadline: $preferredDeadline
    ) {
      id
      value
    }
    globalSettingsChangeEvaluationCooldown(cooldown: $cooldown) {
      id
      value
    }
    globalSettingsChangeEvaluationTTL(ttl: $ttl) {
      id
      value
    }
    globalSettingsChangeDeadlineNotification(
      deadlineNotification: $deadlineNotification
    ) {
      id
      value
    }
  }
`;

const GlobalSetting = () => {
  const { data, loading } = useQuery<GlobalSettings>(GLOBAL_SETTINGS);
  const [globalSettingsChange, { loading: mutationLoading }] = useMutation<
    GlobalSettingsChange,
    GlobalSettingsChangeVars
  >(GLOBAL_SETTINGS_CHANGE);

  const [editing, setEditing] = useState(false);
  const { handleSubmit, register, errors, reset } = useForm<FormTypes>();
  const [deadline, setDeadline] = useState(new Date(Date.now()));
  const [fetched, setFetched] = useState(false);
  const { enqueueSnackbar } = useSnackbar();
  const canEdit = useResources([resources.globalSettings.edit]);

  if (!fetched && data) {
    setFetched(true);
    setDeadline(new Date(data.globalSettingsFindPreferredDeadline.value));
  }

  const submitHandler = (values: FormTypes): void => {
    globalSettingsChange({
      variables: {
        dayStart: +values.dayStart,
        preferredWeeksAhead: +values.weeksAhead,
        preferredDeadline: deadline.toISOString(),
        cooldown: +values.evaluationCooldown,
        ttl: +values.evaluationTTL,
        deadlineNotification: +values.deadlineNotification,
      },
    })
      .then(() => {
        enqueueSnackbar('Globální nastavení úspěšně upraveno', {
          variant: 'success',
        });
        setEditing(false);
        reset();
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se upravit globální nastavení', {
          variant: 'error',
        });
      });
  };

  return (
    <Paper
      title="Globální nastavení"
      loading={loading}
      actions={
        // prettier-ignore
        !editing
          ? [
            <Button
              key="editAction"
              color="primary"
              variant="contained"
              disabled={!canEdit}
              onClick={() => setEditing(true)}
            >
              Upravit
            </Button>,
          ]
          : [
            <LoadingButton
              key="saveAction"
              color="primary"
              variant="contained"
              loading={mutationLoading}
              onClick={handleSubmit(submitHandler)}
            >
              Uložit
            </LoadingButton>,
            <LoadingButton
              key="cancelAction"
              color="secondary"
              variant="contained"
              loading={mutationLoading}
              onClick={() => {
                setEditing(false);
                reset();
                setDeadline(new Date(data?.globalSettingsFindPreferredDeadline.value));
              }}
            >
              Zrušit
            </LoadingButton>,
          ]
      }
    >
      <SimpleTable>
        <SimpleRow name="Začátek dne (hodiny)">
          {editing ? (
            <TextField
              name="dayStart"
              type="number"
              defaultValue={data?.globalSettingsFindDayStart.value}
              inputRef={register({ min: 0, max: 24, required: true })}
              error={errors.dayStart !== undefined}
            />
          ) : (
            data?.globalSettingsFindDayStart.value
          )}
        </SimpleRow>
        <SimpleRow name="Odemčení požadavků (týdny)">
          {editing ? (
            <TextField
              name="weeksAhead"
              type="number"
              defaultValue={data?.globalSettingsFindPreferredWeeksAhead.value}
              inputRef={register({ required: true })}
              error={errors.weeksAhead !== undefined}
            />
          ) : (
            data?.globalSettingsFindPreferredWeeksAhead.value
          )}
        </SimpleRow>
        <SimpleRow name="Deadline požadavků">
          <PreferredDeadline
            deadline={deadline}
            editing={editing}
            onDeadlineChange={d => {
              setDeadline(d);
            }}
          />
        </SimpleRow>
        <SimpleRow name="Notiikace na deadline (hodin předem)">
          {editing ? (
            <TextField
              name="deadlineNotification"
              type="number"
              defaultValue={data?.globalSettingsDeadlineNotification.value}
              inputRef={register({ required: true })}
              error={errors.deadlineNotification !== undefined}
            />
          ) : (
            data?.globalSettingsDeadlineNotification.value
          )}
        </SimpleRow>
        <SimpleRow name="Četnost hodnocení (hodiny)">
          {editing ? (
            <TextField
              name="evaluationCooldown"
              type="number"
              defaultValue={data?.globalSettingsFindEvaluationCooldown.value}
              inputRef={register({ required: true })}
              error={errors.evaluationCooldown !== undefined}
            />
          ) : (
            data?.globalSettingsFindEvaluationCooldown.value
          )}
        </SimpleRow>
        <SimpleRow name="Čas započítávání hodnocení (dny)">
          {editing ? (
            <TextField
              name="evaluationTTL"
              type="number"
              defaultValue={data?.globalSettingsFindEvaluationTTL.value}
              inputRef={register({ required: true })}
              error={errors.evaluationTTL !== undefined}
            />
          ) : (
            data?.globalSettingsFindEvaluationTTL.value
          )}
        </SimpleRow>
      </SimpleTable>
    </Paper>
  );
};

export default withPage(
  GlobalSetting,
  globalSettingsBreadcrumbs,
  globalSettingsResources,
);
