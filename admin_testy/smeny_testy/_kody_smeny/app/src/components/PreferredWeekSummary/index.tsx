import React from 'react';

import SimpleRow from 'components/table/SimpeRow';
import SimpleTable from 'components/table/SimpleTable';

import { PreferredWeekSummaryProps } from './types';

const PreferredWeekSummary = (props: PreferredWeekSummaryProps) => {
  const sortedDays = [];

  props.days.forEach(day => {
    sortedDays[day.order] = (
      <React.Fragment key={day.order}>
        <SimpleRow name={day.name}>
          {`${day.start && `${day.start}:00`} - ${day.end && `${day.end}:00`}`}
        </SimpleRow>
      </React.Fragment>
    );
  });

  return (
    <>
      <SimpleTable
        customFirstColumnName="Den"
        customSecondColumnName="Požadavek"
      >
        {sortedDays}
      </SimpleTable>
    </>
  );
};

export default PreferredWeekSummary;
