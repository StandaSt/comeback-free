import { useLazyQuery, useMutation, useQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useSnackbar } from 'notistack';
import resources from '@shift-planner/shared/config/api/resources';
import React, { useState } from 'react';

import ExtendedHeader from 'pages/weekPlanning/shared/extendedHeader';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import ShiftPlanner from 'components/ShiftPlanner';
import shiftDaysFragment from 'components/ShiftPlanner/fragments/shiftDaysFragment';

import hasAccess from '../../../components/resources/hasAccess';
import rolesToResources from '../../../components/resources/rolesToResources';

import {
  BranchGetNextWeek,
  BranchGetNextWeekVars,
  ShiftWeekClear,
  ShiftWeekClearVars,
  ShiftWeekPublish,
  ShiftWeekPublishVars,
  UserGetLogged,
  WeekPlanningProps,
} from './types';

const USER_GET_LOGGED = gql`
  {
    userGetLogged {
      id
      planableBranches {
        id
        name
      }
    }
  }
`;

const BRANCH_GET_NEXT_WEEK = gql`
  ${shiftDaysFragment}
  query($branchId: Int!, $skipWeeks: Int!) {
    branchGetShiftWeek(branchId: $branchId, skipWeeks: $skipWeeks) {
      id
      published
      branch {
        id
        color
        planners {
          id
          name
          surname
          roles {
            id
            resources {
              id
              name
            }
          }
        }
      }
      startDay
      shiftRoleCount
      ...ShiftDays
    }
  }
`;

const SHIFT_WEEK_PUBLISH = gql`
  mutation($id: Int!, $publish: Boolean!) {
    shiftWeekPublish(id: $id, publish: $publish) {
      id
      published
    }
  }
`;

const SHIFT_WEEK_CLEAR = gql`
  ${shiftDaysFragment}
  mutation($id: Int!) {
    shiftWeekClear(id: $id) {
      id
      shiftRoleCount
      ...ShiftDays
    }
  }
`;

const WeekPlanning: React.FC<WeekPlanningProps> = props => {
  const [branch, setBranch] = useState(-1);
  const { data } = useQuery<UserGetLogged>(USER_GET_LOGGED);
  const [
    branchGetNextWeek,
    {
      data: nextWeekData,
      loading: nextWeekLoading,
      error: nextWeekError,
      refetch,
    },
  ] = useLazyQuery<BranchGetNextWeek, BranchGetNextWeekVars>(
    BRANCH_GET_NEXT_WEEK,
    {
      fetchPolicy: 'cache-and-network',
    },
  );
  const [shiftWeekPublish, { loading }] = useMutation<
    ShiftWeekPublish,
    ShiftWeekPublishVars
  >(SHIFT_WEEK_PUBLISH);

  const [shiftWeekClear, { loading: clearLoading }] = useMutation<
    ShiftWeekClear,
    ShiftWeekClearVars
  >(SHIFT_WEEK_CLEAR);
  const { enqueueSnackbar } = useSnackbar();

  const canEditPublished = useResources([resources.weekPlanning.planPublished]);
  const actionDisabled =
    nextWeekData?.branchGetShiftWeek.published && !canEditPublished;
  const canPlan = useResources([resources.weekPlanning.plan]);
  const canCopyTemplate = useResources([
    resources.weekPlanning.copyFromTemplate,
  ]);

  if (
    data &&
    (!nextWeekData || nextWeekData?.branchGetShiftWeek.branch.id !== branch) &&
    !nextWeekLoading &&
    !nextWeekError
  ) {
    if (data?.userGetLogged.planableBranches.length > 0 && branch > -1)
      branchGetNextWeek({
        variables: { branchId: branch, skipWeeks: props.skipWeeks },
      });
  }

  const publishHandler = (publish: boolean): void => {
    shiftWeekPublish({
      variables: { id: nextWeekData.branchGetShiftWeek.id, publish },
    })
      .then(() => {
        enqueueSnackbar(
          `Týden úspěšně ${publish ? 'zveřejněn' : 'vrácen k úpravě'}`,
          {
            variant: 'success',
          },
        );
      })
      .catch(() => {
        enqueueSnackbar(
          `Týden se nepovedlo ${publish ? 'zveřejnit' : 'vrátit k úpravě'}`,
          {
            variant: 'error',
          },
        );
      });
  };

  const clearHandler = (): void => {
    shiftWeekClear({ variables: { id: nextWeekData.branchGetShiftWeek.id } })
      .then(() => {
        enqueueSnackbar('Týden úspěšně vyprázdněn', { variant: 'success' });
      })
      .catch(() => {
        enqueueSnackbar('Nepovedlo se vyprázdnit týden', { variant: 'error' });
      });
  };

  const [title, setTitle] = useState('');

  let users = nextWeekData?.branchGetShiftWeek.branch.planners || [];
  users = users.filter(u =>
    hasAccess(rolesToResources(u.roles), [resources.weekPlanning.see]),
  );

  const planners = users.filter(u =>
    hasAccess(rolesToResources(u.roles), [resources.weekPlanning.plan]),
  );
  const viewers = users.filter(u => !planners.some(v => v.id === u.id));

  const plannersNames = planners.map(p => `${p.name} ${p.surname}`);
  const viewersNames = viewers.map(v => `${v.name} ${v.surname}`);

  return (
    <Paper
      title={`${props.title} ${title !== '' ? '-' : ''} ${title}`}
      loading={loading || nextWeekLoading}
      style={{ backgroundColor: props.backgroundColor || '' }}
    >
      <ShiftPlanner
        // prettier-ignore
        headExtends={(
          <ExtendedHeader
            selectedBranch={branch}
            branches={data?.userGetLogged.planableBranches || []}
            onBranchChange={(id: number) => setBranch(id)}
            published={nextWeekData?.branchGetShiftWeek.published || false}
            actionLoading={nextWeekLoading || loading  || clearLoading}
            publishDisabled={!nextWeekData || branch < 0}
            publishHandler={publishHandler}
            weekId={nextWeekData?.branchGetShiftWeek.id}
            templateDisabled={!nextWeekData || branch < 0 || actionDisabled || !canCopyTemplate}
            planners={plannersNames}
            viewers={viewersNames}
            branchId={nextWeekData?.branchGetShiftWeek.branch.id}
            clearDisabled={!nextWeekData || branch < 0 || actionDisabled || !canPlan}
            onClear={clearHandler}
            noShiftRoles={nextWeekData?.branchGetShiftWeek.shiftRoleCount === 0}
            peopleDisabled={!nextWeekData}
          />
        )}
        shiftWeek={
          (branch > -1 && nextWeekData?.branchGetShiftWeek) || undefined
        }
        onTitleChange={(t: string) => setTitle(t)}
        refetchWeek={refetch}
        disabledAssigning={actionDisabled || !canPlan}
        disabledRoles={actionDisabled || !canPlan}
        defaultDay={props.defaultDay}
      />
    </Paper>
  );
};

export default WeekPlanning;
