import Info from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import MaterialTable from 'lib/materialTable';
import useResources from 'components/resources/useResources';

import { PlannersIndexProps } from './types';

const InfoIcon = () => <Info color="primary"> </Info>;

const PlannersIndex = (props: PlannersIndexProps) => {
  const router = useRouter();
  const canSeeUsers = useResources([resources.users.see]);

  return (
    <>
      <MaterialTable
        data={props.planners}
        columns={[
          { title: 'Jméno', field: 'name' },
          { title: 'Příjmení', field: 'surname' },
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
    </>
  );
};

export default PlannersIndex;
