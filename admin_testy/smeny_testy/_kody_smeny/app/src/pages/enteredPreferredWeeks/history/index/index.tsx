import React from 'react';
import { gql } from 'apollo-boost';
import { useQuery } from '@apollo/react-hooks';
import dayjs, { Dayjs } from 'dayjs';
import VisibilityIcon from '@material-ui/icons/Visibility';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';

import MaterialTable from 'lib/materialTable';
import withPage from 'components/withPage';
import Paper from 'components/Paper';

import enteredPreferredWeeksResources from '../../resources';

import { ShiftWeekGetStartDay } from './types';
import enteredPreferredWeeksHistoryBreadcrumbs from './breadcrumbs';

const SHIFT_WEEK_GET_START_DAY = gql`
  {
    shiftWeekGetStartDay(skipWeeks: -2)
  }
`;

const Visibility = (): JSX.Element => <VisibilityIcon color="primary" />;

const EnteredPreferredWeeksHistory: React.FC = () => {
  const router = useRouter();
  const { data, loading } = useQuery<ShiftWeekGetStartDay>(
    SHIFT_WEEK_GET_START_DAY,
  );
  const dates = [];

  const formatDate = (date: Dayjs): string => {
    return date.format('DD. MM. YYYY');
  };

  if (data) {
    let firstDate = dayjs(data?.shiftWeekGetStartDay);
    dates.push({ date: firstDate, skipWeeks: -2 });

    for (let i = 1; i < 1000; i++) {
      firstDate = firstDate.subtract(7, 'day');
      dates.push({ date: firstDate, skipWeeks: -2 - i });
    }
  }

  return (
    <Paper title="Zadané požadavky - Historie">
      <MaterialTable
        isLoading={loading}
        columns={[
          {
            title: 'Od',
            render: row => {
              return formatDate(dayjs(row.date));
            },
          },
          {
            title: 'Do',
            render: row => {
              return formatDate(dayjs(row.date).add(6, 'day'));
            },
          },
        ]}
        data={dates}
        actions={[
          {
            icon: Visibility,
            onClick: (_, row) => {
              router.push({
                pathname: routes.enteredPreferredWeeks.history.week,
                query: { skipWeeks: row.skipWeeks },
              });
            },
            tooltip: 'Zobrazit',
          },
        ]}
      />
    </Paper>
  );
};

export default withPage(
  EnteredPreferredWeeksHistory,
  enteredPreferredWeeksHistoryBreadcrumbs,
  enteredPreferredWeeksResources,
);
