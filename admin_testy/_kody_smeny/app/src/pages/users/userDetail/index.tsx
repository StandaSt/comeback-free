import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import resources from '@shift-planner/shared/config/api/resources';
import React from 'react';

import PaperWithTabs from 'components/PaperWithTabs';
import hasAccess from 'components/resources/hasAccess';
import rolesToResources from 'components/resources/rolesToResources';
import withPage from 'components/withPage';

import mainBranchFragment from '../fragments/mainBranchFragment';
import planableBranchesFragment from '../fragments/planableBranchesFragment';
import roleFragment from '../fragments/roleFragment';
import workersShiftRoleTypesFragment from '../fragments/workersShiftRoleTypesFragment';
import workingBranchesFragment from '../fragments/workingBranchesFragment';
import planableShiftRoleTypes from '../fragments/planableShiftRoleTypes';

import Actions from './actions';
import BasicInfo from './basicInfo';
import userDetailBreadcrumbs from './breadcrumbs';
import PlanableBranches from './planableBranches';
import Roles from './roles';
import { UserFindById, UserFindByIdVars } from './types';
import WorkingBranches from './workingBranches';

const USER_FIND_BY_ID = gql`
  ${roleFragment}
  ${planableBranchesFragment}
  ${workingBranchesFragment}
  ${workersShiftRoleTypesFragment}
  ${mainBranchFragment}
  ${planableShiftRoleTypes}
  query($id: Int!) {
    userFindById(id: $id) {
      id
      email
      name
      surname
      createTime
      lastLoginTime
      active
      phoneNumber
      hasOwnCar
      notificationsActivated
      receiveEmails
      ...PlanableShiftRoleTypes
      ...PlanableBranches
      ...Roles
      ...WorkingBranches
      ...WorkersShiftRoleTypes
      ...MainBranch
    }
  }
`;

const UserDetailIndex: React.FC = () => {
  const [userFindById, { data, error, loading }] = useLazyQuery<
    UserFindById,
    UserFindByIdVars
  >(USER_FIND_BY_ID, { fetchPolicy: 'cache-and-network' });
  const router = useRouter();

  if (router.query.userId && !data && !error && !loading) {
    userFindById({ variables: { id: +router.query.userId } });
  }

  const roles = data?.userFindById.roles || [];

  const workingBranchesEnabled = hasAccess(rolesToResources(roles), [
    resources.preferredWeeks.see,
  ]);
  const planningBranchesEnabled = hasAccess(rolesToResources(roles), [
    resources.weekPlanning.see,
    resources.shiftWeekTemplates.see,
    resources.weekSummary.see,
  ]);

  return (
    <PaperWithTabs
      title={
        data ? `${data.userFindById.name} ${data.userFindById.surname}` : ''
      }
      loading={loading}
      tabs={[
        {
          label: 'Základní informace',
          panel: (
            <BasicInfo
              loading={loading}
              user={data ? data.userFindById : undefined}
            />
          ),
        },
        {
          label: 'Role',
          panel: (
            <Roles
              loading={loading}
              roles={data ? data.userFindById.roles : []}
            />
          ),
        },
        {
          label: 'Zařazení',
          panel: (
            <WorkingBranches
              loading={loading}
              workingBranches={data?.userFindById.workingBranches || []}
              userId={data?.userFindById.id}
              workersShiftRoleTypes={
                data?.userFindById.workersShiftRoleTypes || []
              }
              mainBranch={data?.userFindById.mainBranch}
            />
          ),
          disabled: !workingBranchesEnabled,
        },
        {
          label: 'Plánování směn',
          panel: (
            <PlanableBranches
              loading={loading}
              userId={data?.userFindById.id}
              planableBranches={data?.userFindById.planableBranches}
              planableShiftRoleTypes={data?.userFindById.planableShiftRoleTypes}
            />
          ),
          disabled: !planningBranchesEnabled,
        },
        { label: 'Akce', panel: <Actions /> },
      ]}
    />
  );
};

export default withPage(UserDetailIndex, userDetailBreadcrumbs, [
  resources.users.see,
]);
