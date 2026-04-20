import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import React from 'react';

import PaperWithTabs from 'components/PaperWithTabs';
import withPage from 'components/withPage';

import ActionsIndex from './actions';
import BasicInfo from './basicInfo';
import detailBreadcrumbs from './breadcrumbs';
import Planners from './planners';
import detailResources from './resources';
import { BranchFindById } from './types';
import Workers from './workers';

const BRANCH_FIND_BY_ID = gql`
  query($id: Int!) {
    branchFindById(id: $id) {
      id
      name
      color
      active
      planners {
        id
        name
        surname
      }
      workers {
        id
        name
        surname
      }
    }
  }
`;

const DetailIndex = () => {
  const router = useRouter();
  const [branchFindById, { data, loading, error }] = useLazyQuery<
    BranchFindById
  >(BRANCH_FIND_BY_ID);

  if (router.query.branchId && !data && !loading && !error) {
    branchFindById({ variables: { id: +router.query.branchId } });
  }

  return (
    <PaperWithTabs
      title={data?.branchFindById.name || ''}
      loading={loading}
      tabs={[
        {
          label: 'Základní informace',
          panel: (
            <BasicInfo
              id={data?.branchFindById.id}
              active={data?.branchFindById.active}
              name={data?.branchFindById.name}
              loading={loading}
              color={data?.branchFindById.color}
            />
          ),
        },
        {
          label: 'Plánovači',
          panel: <Planners planners={data?.branchFindById.planners || []} />,
        },
        {
          label: 'Zaměstnanci',
          panel: <Workers workers={data?.branchFindById.workers || []} />,
        },
        {
          label: 'Akce',
          panel: (
            <ActionsIndex
              active={data?.branchFindById.active}
              branchId={data?.branchFindById.id}
            />
          ),
        },
      ]}
    />
  );
};

export default withPage(DetailIndex, detailBreadcrumbs, detailResources);
