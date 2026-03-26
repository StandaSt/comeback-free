import { useQuery } from '@apollo/react-hooks';
import { IconButton } from '@material-ui/core';
import InfoIcon from '@material-ui/icons/Info';
import dynamic from 'next/dynamic';
import Link from 'next/link';
import { useRouter } from 'next/router';
import resources from '@shift-planner/shared/config/api/resources';
import historyTranslations from '@shift-planner/shared/config/app/historyTranslations';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React from 'react';

import actionHistoryResources from 'pages/actionHistory/resources';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';
import withPage from 'components/withPage';

import ACTION_HISTORY_FIND_BY_ID from './queries/actionHistory';
import actionHistoryDetailBreadcrumbs from './breadcrumbs';
import { ActionHistoryFindById, ActionHistoryFindByIdVariables } from './types';

const ReactJSON = dynamic(() => import('react-json-view'), { ssr: false });

const ActionHistoryDetail: React.FC = () => {
  const router = useRouter();
  const { data, loading } = useQuery<
    ActionHistoryFindById,
    ActionHistoryFindByIdVariables
  >(ACTION_HISTORY_FIND_BY_ID, {
    variables: {
      id: +router.query.id,
    },
    fetchPolicy: 'no-cache',
  });

  const canSeeUsers = useResources([resources.users.see]);

  return (
    <Paper title="Detail" loading={loading}>
      <SimpleTable>
        <SimpleRow name="Akce">
          {historyTranslations[data?.actionHistoryFindById.name]}
        </SimpleRow>
        <SimpleRow name="Uživatel">
          {`${data?.actionHistoryFindById.user.name} ${data?.actionHistoryFindById.user.surname}`}
          <Link
            href={{
              pathname: routes.users.userDetail,
              query: { userId: data?.actionHistoryFindById.user.id },
            }}
            passHref
          >
            <IconButton color="primary" size="small" disabled={!canSeeUsers}>
              <InfoIcon />
            </IconButton>
          </Link>
        </SimpleRow>
        <SimpleRow name="Datum">
          {dateFormat(
            new Date(data?.actionHistoryFindById.date || Date.now()),
            'dd.mm. yyyy HH:MM:ss',
          )}
        </SimpleRow>
        <SimpleRow name="Dodatečná data">
          <ReactJSON
            src={JSON.parse(data?.actionHistoryFindById.additionalData || '{}')}
            displayDataTypes={false}
            enableClipboard={false}
            displayObjectSize={false}
            collapsed
          />
        </SimpleRow>
      </SimpleTable>
    </Paper>
  );
};

export default withPage(
  ActionHistoryDetail,
  actionHistoryDetailBreadcrumbs,
  actionHistoryResources,
);
