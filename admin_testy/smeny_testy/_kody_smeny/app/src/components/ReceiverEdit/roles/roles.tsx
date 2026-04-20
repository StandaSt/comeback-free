import React from 'react';
import AddIcon from '@material-ui/icons/Add';

import MaterialTable from 'lib/materialTable';
import useSelectedBackground from 'lib/materialTable/useSelectedBackground';

import { Role, RolesProps } from './types';

const Add = (): JSX.Element => <AddIcon color="primary" />;

const Roles: React.FC<RolesProps> = props => {
  const selectedBackground = useSelectedBackground();

  return (
    <MaterialTable
      isLoading={props.loading}
      columns={[{ title: 'Název', field: 'name' }]}
      data={props.roles}
      actions={[
        {
          icon: Add,
          tooltip: 'Zvolit',
          onClick: (_, row: Role) => {
            props.onSelect(row.id);
          },
        },
      ]}
      options={{
        rowStyle: (row: Role) => {
          if (row.id === props.defaultValue) {
            return { backgroundColor: selectedBackground };
          }

          return {};
        },
      }}
    />
  );
};

export default Roles;
