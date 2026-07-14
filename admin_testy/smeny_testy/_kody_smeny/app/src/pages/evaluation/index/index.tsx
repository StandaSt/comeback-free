import React from 'react';
import { useApolloClient } from '@apollo/react-hooks';
import InfoIcon from '@material-ui/icons/Info';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';
import resources from '@shift-planner/shared/config/api/resources';
import PersonIcon from '@material-ui/icons/Person';

import withPage from 'components/withPage';
import Paper from 'components/Paper';
import MaterialTable from 'lib/materialTable';
import useResources from 'components/resources/useResources';

import evaluationBreadcrumbs from './breadcrumbs';
import evaluationResources from './resources';
import USER_PAGINATE from './queries/users';
import { User, UserPaginate, UserPaginateVariables } from './types';

const Info = (): JSX.Element => <InfoIcon color="primary" />;
const Person = (): JSX.Element => <PersonIcon color="primary" />;

const Evaluation: React.FC = () => {
  const client = useApolloClient();
  const router = useRouter();

  const canSeeUser = useResources([resources.users.see]);
  const canSeeTotalScore = useResources([resources.evaluation.history]);

  return (
    <Paper title="Hodnocení">
      <MaterialTable
        columns={[
          { title: 'Příjmení', field: 'surname' },
          { title: 'Jméno', field: 'name' },
          {
            title: 'Hodnocení',
            hidden: !canSeeTotalScore,
            render: (row: User) =>
              row.totalEvaluationScore > 0
                ? `+${row.totalEvaluationScore}`
                : row.totalEvaluationScore,
            filtering: false,
            sorting: false,
          },
        ]}
        options={{ filtering: true }}
        actions={[
          {
            tooltip: 'Detail',
            icon: Info,
            onClick: (_, row: User) => {
              router.push({
                pathname: routes.evaluation.detail,
                query: { id: row.id },
              });
            },
          },
          {
            tooltip: 'Uživatel',
            icon: Person,
            onClick: (_, row: User) => {
              router.push({
                pathname: routes.users.userDetail,
                query: { userId: row.id },
              });
            },
            hidden: !canSeeUser,
          },
        ]}
        data={query => {
          return new Promise((resolve, reject) => {
            const emailFilter = query.filters.find(
              f => f.column.field === 'email',
            );
            const nameFilter = query.filters.find(
              f => f.column.field === 'name',
            );
            const surnameFilter = query.filters.find(
              f => f.column.field === 'surname',
            );

            const filter = {
              email: emailFilter ? emailFilter.value : '',
              name: nameFilter ? nameFilter.value : '',
              surname: surnameFilter ? surnameFilter.value : '',
              active: [true],
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
              .query<UserPaginate, UserPaginateVariables>({
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
                resolve({
                  data: res.data.userPaginate.items,
                  page: query.page,
                  totalCount: res.data.userPaginate.totalCount,
                });
              })
              .catch(reject);
          });
        }}
      />
    </Paper>
  );
};

export default withPage(Evaluation, evaluationBreadcrumbs, evaluationResources);
