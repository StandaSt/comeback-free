import { useQuery } from '@apollo/react-hooks';
import { Button } from '@material-ui/core';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import React from 'react';
import routes from '@shift-planner/shared/config/app/routes';

import rolesResources from 'pages/roles/index/resources';
import resourceDetailBreadcrumbs from 'pages/roles/resourceDetail/breadcrumbs';
import {
  ResourceFindById,
  ResourceFindByIdVars,
} from 'pages/roles/resourceDetail/types';
import Paper from 'components/Paper';
import withPage from 'components/withPage';

import ResourceDetail from './resourceDetail';

const RESOURCE_FIND_BY_ID = gql`
  query($id: Int!) {
    resourceFindById(id: $id) {
      id
      name
      label
      description
      category {
        id
        name
        label
      }
      minimalCount
      requires {
        id
        name
        label
      }
      requiredBy {
        id
        name
        label
      }
      roles {
        id
        name
      }
    }
  }
`;

const ResourceDetailIndex: React.FC = () => {
  const router = useRouter();
  const { data, loading } = useQuery<ResourceFindById, ResourceFindByIdVars>(
    RESOURCE_FIND_BY_ID,
    {
      variables: { id: +router.query.resourceId },
      fetchPolicy: 'no-cache',
    },
  );

  return (
    <>
      <Paper
        title={data ? data.resourceFindById.label : ''}
        loading={loading}
        actions={[
          <Button
            key="actionZoom"
            color="primary"
            variant="contained"
            onClick={() => {
              router.push({
                pathname: `${routes.roles.index}`,
                hash: `#resource-${+data?.resourceFindById.id}`,
                query: { resourceId: data?.resourceFindById.id },
              });
            }}
          >
            Přiblížit
          </Button>,
        ]}
      >
        <ResourceDetail resource={data ? data.resourceFindById : undefined} />
      </Paper>
    </>
  );
};

export default withPage(
  ResourceDetailIndex,
  resourceDetailBreadcrumbs,
  rolesResources,
);
