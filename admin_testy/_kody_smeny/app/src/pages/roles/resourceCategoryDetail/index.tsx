import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import React from 'react';

import rolesResources from 'pages/roles/index/resources';
import Paper from 'components/Paper';
import withPage from 'components/withPage';

import resourceCategoryDetailBreadcrumbs from './breadcrumbs';
import ResourceCategoryDetail from './resourceCategoryDetail';
import {
  ResourceCategoryFindById,
  ResourceCategoryFindByIdVars,
} from './types';

const RESOURCE_CATEGORY_FIND_BY_ID = gql`
  query($id: Int!) {
    resourceCategoryFindById(id: $id) {
      id
      name
      resources {
        id
        name
      }
    }
  }
`;

const ResourceCategoryDetailIndex: React.FC = () => {
  const router = useRouter();
  const [resourceCategoryFindById, { data, error, loading }] = useLazyQuery<
    ResourceCategoryFindById,
    ResourceCategoryFindByIdVars
  >(RESOURCE_CATEGORY_FIND_BY_ID);
  if (+router.query.categoryId && !data && !error && !loading) {
    resourceCategoryFindById({ variables: { id: +router.query.categoryId } });
  }

  return (
    <>
      <Paper title={data ? data.resourceCategoryFindById.name : ''}>
        <ResourceCategoryDetail
          category={data ? data.resourceCategoryFindById : undefined}
        />
      </Paper>
    </>
  );
};

export default withPage(
  ResourceCategoryDetailIndex,
  resourceCategoryDetailBreadcrumbs,
  rolesResources,
);
