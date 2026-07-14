import React from 'react';

import PaperWithTabs from 'components/PaperWithTabs';

import { ReceiverEditProps } from './types';
import RolesIndex from './roles';
import ResourcesIndex from './resources';

const ReceiverEdit: React.FC<ReceiverEditProps> = props => {
  const roleSelectHandler = (id: number): void => {
    props.onSelect({ roleId: id });
  };

  const resourceSelectHandler = (id: number): void => {
    props.onSelect({ resourceId: id });
  };

  return (
    <PaperWithTabs
      title={props.title}
      loading={props.loading}
      tabs={[
        {
          label: 'Pravomoce',
          panel: (
            <ResourcesIndex
              onSelect={resourceSelectHandler}
              defaultValue={props.defaultValues?.resourceId}
            />
          ),
        },
        {
          label: 'Role',
          panel: (
            <RolesIndex
              onSelect={roleSelectHandler}
              defaultValue={props.defaultValues?.roleId}
            />
          ),
        },
      ]}
    />
  );
};

export default ReceiverEdit;
