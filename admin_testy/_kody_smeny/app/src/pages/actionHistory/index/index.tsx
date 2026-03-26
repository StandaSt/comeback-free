import { useApolloClient } from '@apollo/react-hooks';
import InfoIcon from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import historyTranslations from '@shift-planner/shared/config/app/historyTranslations';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React from 'react';

import MaterialTable from 'lib/materialTable';
import DateFilter from 'lib/materialTable/dateFilter';
import Paper from 'components/Paper';
import withPage from 'components/withPage';

import actionHistoryResources from '../resources';

import actionHistoryBreadcrumbs from './breadcrumbs';
import ACTION_HISTORY_PAGINATE from './queries/actionHistory';
import {
  ActionHistory,
  ActionHistoryPaginate,
  ActionHistoryPaginateVariables,
} from './types';

const Info = () => <InfoIcon color="primary" />;

const ActionHistoryIndex: React.FC = () => {
  const client = useApolloClient();
  const router = useRouter();

  return (
    <Paper title="Logování">
      <MaterialTable
        columns={[
          { title: 'Jméno', field: 'user.name' },
          { title: 'Příjmení', field: 'user.surname' },
          {
            title: 'Akce',
            field: 'name',
            render: (row: ActionHistory) => historyTranslations[row.name],
            lookup: Object.keys(historyTranslations)
              .map(key => ({ [key]: historyTranslations[key] }))
              .reduce((prev, cur) => ({ ...prev, ...cur })),
            sorting: false,
          },
          {
            title: 'Datum',
            field: 'date',
            render: (row: ActionHistory) =>
              dateFormat(new Date(row.date), 'dd.mm. yyyy HH:MM:ss'),
            // eslint-disable-next-line react/display-name
            filterComponent: DateFilter,
          },
        ]}
        data={query =>
          new Promise((resolve, reject) => {
            let orderBy = {};
            if (query.orderBy) {
              if (query.orderBy.field === 'date') {
                orderBy = {
                  orderBy: {
                    fieldName: `actionHistory.${query.orderBy.field}`,
                    type: query.orderDirection.toUpperCase(),
                  },
                };
              } else {
                orderBy = {
                  orderBy: {
                    fieldName: query.orderBy.field,
                    type: query.orderDirection.toUpperCase(),
                  },
                };
              }
            }
            client
              .query<ActionHistoryPaginate, ActionHistoryPaginateVariables>({
                query: ACTION_HISTORY_PAGINATE,
                variables: {
                  limit: query.pageSize,
                  offset: query.pageSize * query.page,
                  filter: {
                    userName: query.filters.find(
                      f => f.column.field === 'user.name',
                    )?.value,
                    userSurname:
                      query.filters.find(f => f.column.field === 'user.surname')
                        ?.value || '',
                    name:
                      query.filters.find(f => f.column.field === 'name')
                        ?.value || '',
                    date:
                      query.filters
                        .find(f => f.column.field === 'date')
                        ?.value.toISOString() || undefined,
                  },
                  ...orderBy,
                },
                fetchPolicy: 'no-cache',
              })
              .then(res => {
                resolve({
                  data: res.data.actionHistoryPaginate.items,
                  page: query.page,
                  totalCount: res.data.actionHistoryPaginate.totalCount,
                });
              })
              .catch(() => reject());
          })
        }
        actions={[
          {
            icon: Info,
            tooltip: 'Detail',
            onClick: (e, row: ActionHistory) => {
              router.push({
                pathname: routes.actionHistory.detail,
                query: { id: row.id },
              });
            },
          },
        ]}
        options={{ filtering: true, pageSize: 20, pageSizeOptions: [20] }}
      />
    </Paper>
  );
};

export default withPage(
  ActionHistoryIndex,
  actionHistoryBreadcrumbs,
  actionHistoryResources,
);
