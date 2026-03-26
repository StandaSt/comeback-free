import { useMutation, useQuery } from '@apollo/react-hooks';
import {
  Button,
  Dialog,
  DialogActions,
  DialogTitle,
  Theme,
  Typography,
} from '@material-ui/core';
import { gql } from 'apollo-boost';
import React, { useState } from 'react';
import dayjs from 'dayjs';
import { makeStyles } from '@material-ui/core/styles';
import { useSnackbar } from 'notistack';

import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';

import Paper from '../Paper';
import LoadingButton from '../LoadingButton';
import Loading from '../withPage/Loading';

import {
  PreferredWeekConfirm,
  PreferredWeekConfirmVariables,
  WorkingInterval,
  WorkingWeekGetFromCurrentWeek,
  WorkingWeekGetFromCurrentWeekVars,
  WorkingWeekProps,
} from './types';

const WORKING_WEEK_GET_FROM_CURRENT_WEEK = gql`
  fragment Day on WorkingDay {
    workingIntervals {
      from
      to
      halfHour
      branchName
      shiftRoleType
    }
  }
  query($skipWeeks: Int!) {
    workingWeekGetFromCurrentWeek(skipWeeks: $skipWeeks) {
      totalBranchCount
      publishedBranches
      monday {
        ...Day
      }
      tuesday {
        ...Day
      }
      wednesday {
        ...Day
      }
      thursday {
        ...Day
      }
      friday {
        ...Day
      }
      saturday {
        ...Day
      }
      sunday {
        ...Day
      }
      preferredWeek {
        id
        confirmed
      }
    }
    shiftWeekGetStartDay(skipWeeks: $skipWeeks)
  }
`;

const PREFERRED_WEEK_CONFIRM = gql`
  mutation PreferredWeeekConfirm($weekId: Int!) {
    preferredWeekConfirm(weekId: $weekId) {
      id
      confirmed
    }
  }
`;

const useStyles = makeStyles((theme: Theme) => ({
  red: {
    color: theme.palette.error.main,
  },
  green: {
    color: theme.palette.success.main,
  },
}));

const WorkingWeek: React.FC<WorkingWeekProps> = props => {
  const classes = useStyles();
  const { data, loading } = useQuery<
    WorkingWeekGetFromCurrentWeek,
    WorkingWeekGetFromCurrentWeekVars
  >(WORKING_WEEK_GET_FROM_CURRENT_WEEK, {
    variables: { skipWeeks: props.skipWeeks },
    fetchPolicy: 'cache-and-network',
  });
  const [confirm, { loading: confirmLoading }] = useMutation<
    PreferredWeekConfirm,
    PreferredWeekConfirmVariables
  >(PREFERRED_WEEK_CONFIRM);
  const [confirmModal, setConfirmModal] = useState(false);
  const { enqueueSnackbar } = useSnackbar();

  const mapIntervals = (intervals: WorkingInterval[], day: string) =>
    // prettier-ignore
    intervals.length > 0
      ? intervals.map((i) => (
        <div key={`${day}-${i.from}-${i.to}`}>{`${i.from}:${i.halfHour?"30":"00"}-${i.to + 1}:00 - ${i.branchName} (${i.shiftRoleType})`}</div>
      ))
      : '-';

  const mapDay = (day: string) =>
    mapIntervals(
      data?.workingWeekGetFromCurrentWeek[day].workingIntervals || [],
      day,
    );

  const startDate = dayjs(data?.shiftWeekGetStartDay);
  const endDate = startDate.add(6, 'day');

  const allBranchesPublished =
    data?.workingWeekGetFromCurrentWeek.publishedBranches.length ===
    data?.workingWeekGetFromCurrentWeek.totalBranchCount;

  const modalCloseHandler = () => {
    setConfirmModal(false);
  };

  const confirmHandler = () => {
    confirm({
      variables: {
        weekId: data?.workingWeekGetFromCurrentWeek.preferredWeek.id,
      },
    })
      .then(() => {
        enqueueSnackbar('Směny úspěšně akceptovány', { variant: 'success' });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se akceptovat směny', { variant: 'error' });
      });
    modalCloseHandler();
  };

  return (
    <>
      <Paper
        title={`${props.title} - ${startDate.format(
          'DD. MM. YYYY',
        )} - ${endDate.format('DD. MM. YYYY')}`}
        style={{ backgroundColor: props.backgroundColor || '' }}
        loading={loading}
        actionsFullWidth
        actions={[
          <Button
            key={0}
            color="primary"
            variant="contained"
            fullWidth
            disabled={
              !allBranchesPublished ||
              data?.workingWeekGetFromCurrentWeek.preferredWeek.confirmed
            }
            onClick={() => setConfirmModal(true)}
          >
            {data?.workingWeekGetFromCurrentWeek.preferredWeek.confirmed
              ? 'Již akceptováno'
              : 'Akceptovat směny'}
          </Button>,
        ]}
      >
        <Typography
          className={!allBranchesPublished ? classes.red : classes.green}
        >
          {`Publikované pobočky: ${
            data?.workingWeekGetFromCurrentWeek.publishedBranches.join(', ') ||
            ''
          } (${
            data?.workingWeekGetFromCurrentWeek.publishedBranches.length || '0'
          } z ${data?.workingWeekGetFromCurrentWeek.totalBranchCount || '0'})`}
        </Typography>
        <SimpleTable
          customFirstColumnName="Den"
          customSecondColumnName="Hodiny"
        >
          <SimpleRow name="Pondělí">{mapDay('monday')}</SimpleRow>
          <SimpleRow name="Úterý">{mapDay('tuesday')}</SimpleRow>
          <SimpleRow name="Středa">{mapDay('wednesday')}</SimpleRow>
          <SimpleRow name="Čtvrtek">{mapDay('thursday')}</SimpleRow>
          <SimpleRow name="Pátek">{mapDay('friday')}</SimpleRow>
          <SimpleRow name="Sobota">{mapDay('saturday')}</SimpleRow>
          <SimpleRow name="Neděle">{mapDay('sunday')}</SimpleRow>
        </SimpleTable>
      </Paper>
      <Dialog open={confirmModal}>
        <DialogTitle>Tato akce vyžaduje potvrzení</DialogTitle>
        <DialogActions>
          <LoadingButton
            color="primary"
            loading={confirmLoading}
            onClick={confirmHandler}
          >
            Potvrdit
          </LoadingButton>
          <LoadingButton
            color="secondary"
            loading={confirmLoading}
            onClick={modalCloseHandler}
          >
            Zrušit
          </LoadingButton>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default WorkingWeek;
