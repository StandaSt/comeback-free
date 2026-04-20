import InfoIcon from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import MaterialTable from 'lib/materialTable';
import { UsersProps } from 'pages/roles/roleDetail/users/types';

const Info = (): JSX.Element => <InfoIcon color="primary" />;

const Users: React.FC<UsersProps> = props => {
  const router = useRouter();

  return (
    <>
      <MaterialTable
        data={props.users}
        columns={[
          { title: 'Email', field: 'email' },
          { title: 'Jméno', field: 'name' },
          { title: 'Příjmení', field: 'surname' },
          {
            title: 'Status',
            field: 'acitve',
            render: data => (data.active ? 'Aktivní' : 'Neaktivní'),
            customFilterAndSearch: (filter, rowData) => {
              if (filter.length === 0) return true;

              return filter.some(f => rowData.active.toString() === f);
            },
            lookup: { true: 'Aktivní', false: 'Neaktivní' },
          },
        ]}
        options={{ filtering: true }}
        actions={[
          {
            icon: Info,
            tooltip: 'Detail',
            onClick: (e, rowData) => {
              router.push({
                pathname: routes.users.userDetail,
                query: { userId: rowData.id },
              });
            },
          },
        ]}
      />
    </>
  );
};

export default Users;
