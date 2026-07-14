import React from 'react';
import { useRouter } from 'next/router';
import { useQuery } from '@apollo/react-hooks';
import resources from '@shift-planner/shared/config/api/resources';

import withPage from 'components/withPage';
import PaperWithTabs from 'components/PaperWithTabs';
import useResources from 'components/resources/useResources';

import detailResources from '../../branches/detail/resources';

import detailBreadcrumbs from './breadcrumbs';
import USER_FIND_BY_ID from './queries/user';
import { UserFindById, UserFindByIdVariables } from './types';
import AddEvaluationIndex from './addEvaluation';
import HistoryIndex from './history';

const Detail: React.FC = () => {
  const router = useRouter();
  const { data, loading } = useQuery<UserFindById, UserFindByIdVariables>(
    USER_FIND_BY_ID,
    {
      fetchPolicy: 'cache-and-network',
      variables: { id: +router.query.id },
    },
  );
  const canAdd = useResources([resources.evaluation.add]);
  const canSeeHistory = useResources([resources.evaluation.history]);

  const tabs = [];
  if (canAdd) {
    tabs.push({
      label: 'Přidat hodnocení',
      panel: <AddEvaluationIndex userId={data?.userFindById.id} />,
      disabled: !canAdd,
    });
  }
  if (canSeeHistory) {
    tabs.push({
      label: 'Historie',
      panel: (
        <HistoryIndex
          evaluation={data?.userFindById.evaluation}
          totalScore={data?.userFindById.totalEvaluationScore}
        />
      ),
      disabled: !canSeeHistory,
    });
  }

  return (
    <PaperWithTabs
      title={`${data?.userFindById.name} ${data?.userFindById.surname} `}
      loading={loading}
      tabs={tabs}
    />
  );
};
export default withPage(Detail, detailBreadcrumbs, detailResources);
