import React from 'react';
import { useQuery } from '@apollo/react-hooks';

import ROLES_QUERY from '../queries/roles';
import { PanelProps } from '../types';

import { RolesQuery } from './types';
import Roles from './roles';

const RolesIndex: React.FC<PanelProps> = props => {
  const { data: rolesData, loading: rolesLoading } = useQuery<RolesQuery>(
    ROLES_QUERY,
  );

  return (
    <Roles
      roles={rolesData?.roleFindAll || []}
      loading={rolesLoading}
      onSelect={props.onSelect}
      defaultValue={props.defaultValue}
    />
  );
};

export default RolesIndex;
