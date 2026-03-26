import React from 'react';

import Paper from 'components/Paper';
import MaterialTable from 'lib/materialTable';

import { Evaluation, MyEvaluationProps } from './types';

const MyEvaluation: React.FC<MyEvaluationProps> = props => {
  return (
    <Paper title="Moje hodnocení" loading={props.loading}>
      <MaterialTable
        columns={[
          {
            title: 'Změna',
            render: (data: Evaluation) => (data.positive ? '+1' : '-1'),
          },
          { title: 'Zdůvodnění', field: 'description' },
        ]}
        data={props.evaluation}
      />
    </Paper>
  );
};

export default MyEvaluation;
