import React from 'react';
import AddIcon from '@material-ui/icons/Add';

import MaterialTable from 'lib/materialTable';
import useSelectedBackground from 'lib/materialTable/useSelectedBackground';

import { Resource, ResourcesProps } from './types';

const Add = (): JSX.Element => <AddIcon color="primary" />;

const Resources: React.FC<ResourcesProps> = props => {
  const selectedBackground = useSelectedBackground();

  return (
    <MaterialTable
      isLoading={props.loading}
      columns={[
        { title: 'Katergorie', field: 'category.label' },
        { title: 'Název', field: 'label' },
        { title: 'Popis', field: 'description' },
      ]}
      data={props.resources.sort((resource1, resource2) => {
        const category1 = resource1.category.label;
        const category2 = resource2.category.label;
        if (category1 > category2) return 1;

        if (category1 === category2) return 0;

        return -1;
      })}
      actions={[
        {
          icon: Add,
          tooltip: 'Zvolit',
          onClick: (_, row: Resource) => {
            props.onSelect(row.id);
          },
        },
      ]}
      options={{
        rowStyle: (row: Resource) => {
          if (row.id === props.defaultValue) {
            return { backgroundColor: selectedBackground };
          }

          return {};
        },
      }}
    />
  );
};

export default Resources;
