import Info from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import MaterialTable from 'lib/materialTable';
import { WorkersProps } from 'pages/branches/detail/workers/types';
import useResources from 'components/resources/useResources';

const InfoIcon = () => <Info color="primary"> </Info>;

const Workers = (props: WorkersProps) => {
  const router = useRouter();
  const canSeeUsers = useResources([resources.users.see]);

  return (
    <MaterialTable
      data={props.workers}
      columns={[
        { field: 'name', title: 'Jméno' },
        { field: 'surname', title: 'Příjmení' },
      ]}
      actions={
        (canSeeUsers && [
          {
            icon: InfoIcon,
            tooltip: 'Detail',
            onClick: (e, rowData) =>
              router.push({
                pathname: routes.users.userDetail,
                query: { userId: rowData.id },
              }),
          },
        ]) ||
        []
      }
    />
  );
};

export default Workers;
