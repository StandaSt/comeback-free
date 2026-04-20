import React from 'react';
import dayjs from 'dayjs';
import { Typography } from '@material-ui/core';

import MaterialTable from 'lib/materialTable';

import { EvaluationSingle } from '../types';

import { HistoryIndexProps } from './types';

const HistoryIndex: React.FC<HistoryIndexProps> = props => {
  return (
    <>
      <Typography variant="h6">
        {`Celkové skóre: ${props.totalScore}`}
      </Typography>
      <MaterialTable
        columns={[
          {
            title: 'Hodnotil',
            field: 'evaluator.name',
            render: (row: EvaluationSingle) =>
              `${row.evaluator.name} ${row.evaluator.surname}`,
          },
          {
            title: 'Změna',
            render: (row: EvaluationSingle) => (row.positive ? '+1' : '-1'),
          },
          {
            title: 'Důvod',
            render: (row: EvaluationSingle) => row.description,
          },
          {
            title: 'Datum',
            render: (row: EvaluationSingle) =>
              dayjs(row.date).format('DD. MM. YYYY mm:ss'),
          },
        ]}
        data={props.evaluation.sort((e1, e2) => {
          const e1date = new Date(e1.date);
          const e2date = new Date(e2.date);
          if (e1date < e2date) {
            return 1;
          }
          if (e1date > e2date) {
            return -1;
          }

          return 0;
        })}
      />
    </>
  );
};

export default HistoryIndex;
