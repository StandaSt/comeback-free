import React from 'react';
import { useQuery } from '@apollo/react-hooks';

import withPage from 'components/withPage';

import myEvaluationBreadcrumbs from './breadcrumbs';
import MyEvaluation from './myEvaluation';
import EVALUATION_QUERY from './queries/evaluation';
import { EvaluationQuery } from './types';

const MyEvaluationIndex: React.FC = () => {
  const { data: evaluationData, loading: evaluationLoading } = useQuery<
    EvaluationQuery
  >(EVALUATION_QUERY, { fetchPolicy: 'no-cache' });

  return (
    <MyEvaluation
      loading={evaluationLoading}
      evaluation={evaluationData?.userGetLogged.evaluation || []}
    />
  );
};

export default withPage(MyEvaluationIndex, myEvaluationBreadcrumbs);
