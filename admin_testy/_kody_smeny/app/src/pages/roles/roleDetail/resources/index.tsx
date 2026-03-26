import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import React from 'react';

import Resources from './resources';
import { RoleFindById, RoleFindByIdVars } from './types';

const ROLE_FIND_BY_ID = gql`
  query($id: Int!) {
    roleFindById(id: $id) {
      id
      resources {
        id
        label
      }
    }
  }
`;

const ResourcesIndex: React.FC = () => {
  const router = useRouter();
  const [roleFindById, { data, error, loading }] = useLazyQuery<
    RoleFindById,
    RoleFindByIdVars
  >(ROLE_FIND_BY_ID, { fetchPolicy: 'no-cache' });

  if (router.query.roleId && !data && !error && !loading) {
    roleFindById({ variables: { id: +router.query.roleId } });
  }

  return (
    <>
      <Resources resources={data ? data.roleFindById.resources : undefined} />
    </>
  );
};

export default ResourcesIndex;
