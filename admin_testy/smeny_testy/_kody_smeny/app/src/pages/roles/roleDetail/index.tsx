import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import React from 'react';

import rolesResources from 'pages/roles/index/resources';
import ActionsIndex from 'pages/roles/roleDetail/actions';
import PaperWithTabs from 'components/PaperWithTabs';
import withPage from 'components/withPage';

import BasicInfo from './basicInfo';
import roleDetailBreadcrumbs from './breadcrumbs';
import ResourcesIndex from './resources';
import { RoleFindById, RoleFindByIdVars } from './types';
import UsersIndex from './users';

const ROLE_FIND_BY_ID = gql`
  query($id: Int!) {
    roleFindById(id: $id) {
      id
      name
      maxUsers
      userCount
      registrationDefault
      sortIndex
    }
  }
`;

const RoleDetailIndex: React.FC = () => {
  const router = useRouter();
  const [roleFindById, { data, error, loading }] = useLazyQuery<
    RoleFindById,
    RoleFindByIdVars
  >(ROLE_FIND_BY_ID);

  if (router.query.roleId && !data && !error && !loading) {
    roleFindById({ variables: { id: +router.query.roleId } });
  }

  return (
    <>
      <PaperWithTabs
        title={data ? data.roleFindById.name : ''}
        tabs={[
          {
            label: 'Základní informace',
            // eslint-disable-next-line jsx-a11y/aria-role
            panel: <BasicInfo loading={loading} role={data?.roleFindById} />,
          },
          { label: 'Pravomoce', panel: <ResourcesIndex /> },
          {
            label: 'Uživatelé',
            panel: <UsersIndex />,
          },
          { label: 'Akce', panel: <ActionsIndex /> },
        ]}
      />
    </>
  );
};

export default withPage(RoleDetailIndex, roleDetailBreadcrumbs, rolesResources);
