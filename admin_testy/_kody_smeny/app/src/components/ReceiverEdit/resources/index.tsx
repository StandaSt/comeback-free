import React from 'react';
import { useQuery } from '@apollo/react-hooks';

import { PanelProps } from '../types';
import RESOURCES_QUERY from '../queries/resources';

import Resources from './resources';
import { ResourcesQuery } from './types';

const ResourcesIndex: React.FC<PanelProps> = props => {
  const { data: resourcesData, loading: resourcesLoading } = useQuery<
    ResourcesQuery
  >(RESOURCES_QUERY);

  return (
    <Resources
      resources={resourcesData?.resourceFindAll || []}
      loading={resourcesLoading}
      onSelect={props.onSelect}
      defaultValue={props.defaultValue}
    />
  );
};

export default ResourcesIndex;
