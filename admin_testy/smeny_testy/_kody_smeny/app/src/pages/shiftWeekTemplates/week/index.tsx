import { useLazyQuery } from '@apollo/react-hooks';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import React, { useState } from 'react';

import shiftWeekTemplatesResources from 'pages/shiftWeekTemplates/index/resources';
import Paper from 'components/Paper';
import ShiftPlannerIndex from 'components/ShiftPlanner';
import shiftDayFragment from 'components/ShiftPlanner/fragments/shiftDayFragment';
import withPage from 'components/withPage';

import weekBreadcrumbs from './breadcrumbs';

const SHIFT_WEEK_TEMPLATE_FIND_BY_ID = gql`
  ${shiftDayFragment}
  fragment ShiftDays on ShiftWeekTemplate {
    shiftWeek {
      shiftDays {
        ...ShiftDay
      }
      branch {
        id
        color
      }
    }
  }
  query($id: Int!) {
    shiftWeekTemplateFindById(id: $id) {
      id
      ...ShiftDays
    }
  }
`;

const Week: React.FC = () => {
  const router = useRouter();
  const [title, setTitle] = useState('');
  const [
    shiftWeekTemplateFindById,
    { data, called, loading, refetch },
  ] = useLazyQuery(SHIFT_WEEK_TEMPLATE_FIND_BY_ID);

  if (!called && router.query.id) {
    shiftWeekTemplateFindById({ variables: { id: +router.query.id } });
  }

  return (
    <Paper title={`Šablona ${title && '-'} ${title}`} loading={loading}>
      <ShiftPlannerIndex
        shiftWeek={data?.shiftWeekTemplateFindById.shiftWeek}
        onTitleChange={setTitle}
        refetchWeek={refetch}
        disabledAssigning
        hideDate
      />
    </Paper>
  );
};

export default withPage(Week, weekBreadcrumbs, shiftWeekTemplatesResources);
