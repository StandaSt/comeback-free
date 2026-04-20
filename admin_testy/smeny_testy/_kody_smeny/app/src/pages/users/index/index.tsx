import { useApolloClient } from '@apollo/react-hooks';
import { Button } from '@material-ui/core';
import InfoIcon from '@material-ui/icons/Info';
import { gql } from 'apollo-boost';
import Link from 'next/link';
import { useRouter } from 'next/router';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import MaterialTable from 'lib/materialTable';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import withPage from 'components/withPage';

import usersBreadcrumbs from './breadcrumbs';
import usersResources from './resources';
import { UserPaginate, UserPaginateVars } from './types';

const USER_PAGINATE = gql`
  query(
    $limit: Int!
    $offset: Int!
    $filter: UserFilterArg
    $orderBy: OrderByArg
  ) {
    userPaginate {
      items(
        limit: $limit
        offset: $offset
        filter: $filter
        orderBy: $orderBy
      ) {
        id
        name
        surname
        email
        active
        approved
        phoneNumber
        notificationsActivated
      }
      totalCount(filter: $filter)
    }
  }
`;

const Info = (): JSX.Element => <InfoIcon color="primary" />;

const UsersIndex: React.FC = () => {
  const client = useApolloClient();
  const router = useRouter();

  const canAdd = useResources([resources.users.add]);

  return (
    <Paper
      title="Uživatelé"
      actions={[
        <Link key="actionAdd" href={routes.users.addUser}>
          <Button color="primary" variant="contained" disabled={!canAdd}>
            Přidat uživatele
          </Button>
        </Link>,
      ]}
    >
      <MaterialTable
        data={query =>
          new Promise((resolve, reject) => {
            const emailFilter = query.filters.find(
              f => f.column.field === 'email',
            );
            const nameFilter = query.filters.find(
              f => f.column.field === 'name',
            );
            const surnameFilter = query.filters.find(
              f => f.column.field === 'surname',
            );
            const activeFilter = query.filters.find(
              f => f.column.field === 'active',
            );
            const notificationsFilter = query.filters.find(
              f => f.column.field === 'notificationsActivated',
            );

            const filter = {
              email: emailFilter ? emailFilter.value : '',
              name: nameFilter ? nameFilter.value : '',
              surname: surnameFilter ? surnameFilter.value : '',
              active: activeFilter
                ? activeFilter.value.map(v => v === 'active')
                : [true, false],
              approved: activeFilter
                ? activeFilter.value.map(a => a !== 'waiting')
                : [],
              notificationsActivated: notificationsFilter
                ? notificationsFilter?.value.map(n => n === 'true')
                : [],
            };

            const orderBy = query.orderBy
              ? {
                  fieldName: query.orderBy.field.toString(),
                  type: query.orderDirection.toUpperCase(),
                }
              : {
                  fieldName: 'email',
                  type: 'ASC',
                };

            client
              .query<UserPaginate, UserPaginateVars>({
                query: USER_PAGINATE,
                variables: {
                  limit: query.pageSize,
                  offset: query.page * query.pageSize,
                  filter,
                  orderBy,
                },
                fetchPolicy: 'no-cache',
              })
              .then(res => {
                if (res.data) {
                  resolve({
                    data: res.data.userPaginate.items,
                    page: query.page,
                    totalCount: res.data.userPaginate.totalCount,
                  });
                } else {
                  reject();
                }
              });
          })
        }
        actions={[
          {
            icon: Info,
            tooltip: 'Detail',
            onClick: (e, rowData) => {
              router.push({
                pathname: routes.users.userDetail,
                query: { userId: rowData.id },
              });
            },
          },
        ]}
        options={{
          filtering: true,
          pageSize: 25,
          pageSizeOptions: [25, 50, 100, 200],
        }}
        columns={[
          { title: 'Email', field: 'email' },
          { title: 'Jméno', field: 'name' },
          { title: 'Příjmení', field: 'surname' },
          { title: 'Telefonní číslo', field: 'phoneNumber' },
          {
            title: 'Status',
            field: 'active',
            render: data => {
              if (!data.approved) return 'Čeká na schválení';
              if (data.active) return 'Aktivní';

              return 'Neaktivní';
            },
            lookup: {
              active: 'Aktivní',
              inactive: 'Neaktivní',
              waiting: 'Čeká na schválení',
            },
          },
          {
            title: 'Aktivované notifikace',
            field: 'notificationsActivated',
            lookup: {
              true: 'Ano',
              false: 'Ne',
            },
            sorting: false,
          },
        ]}
      />
    </Paper>
  );
};

export default withPage(UsersIndex, usersBreadcrumbs, usersResources);
