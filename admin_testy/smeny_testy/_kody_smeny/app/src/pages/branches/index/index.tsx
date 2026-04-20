import { useQuery } from '@apollo/react-hooks';
import { Button, Theme } from '@material-ui/core';
import InfoIcon from '@material-ui/icons/Info';
import { gql } from 'apollo-boost';
import { useRouter } from 'next/router';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';
import { makeStyles } from '@material-ui/core/styles';

import MaterialTable from 'lib/materialTable';
import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import withPage from 'components/withPage';

import branchesBreadcrumbs from './breadcrumbs';
import branchesResources from './resources';
import { BranchFindAll } from './types';

const BRANCH_FIND_ALL = gql`
  {
    branchFindAll {
      id
      name
      active
      color
    }
  }
`;

const Info = () => <InfoIcon color="primary" />;

const useStyles = makeStyles((theme: Theme) => ({
  colorDot: {
    height: theme.spacing(3),
    width: theme.spacing(3),
    borderRadius: '100%',
  },
}));

const BranchesIndex = () => {
  const classes = useStyles();
  const router = useRouter();
  const { data, loading } = useQuery<BranchFindAll>(BRANCH_FIND_ALL, {
    fetchPolicy: 'no-cache',
  });
  const canAdd = useResources([resources.branches.add]);

  return (
    <>
      <Paper
        title="Pobočky"
        actions={[
          <Button
            key="addAction"
            color="primary"
            variant="contained"
            disabled={!canAdd}
            onClick={() => router.push(routes.branches.add)}
          >
            Přidat pobočku
          </Button>,
        ]}
      >
        <MaterialTable
          data={data?.branchFindAll}
          columns={[
            { title: 'Název', field: 'name' },
            {
              title: 'Status',
              field: 'active',
              render: row => (row.active ? 'Aktivní' : 'Neaktivní'),
              lookup: { true: 'Aktivní', false: 'Neaktivní' },
            },
            {
              title: 'Barva',
              // eslint-disable-next-line react/display-name
              render: row => (
                <div
                  className={classes.colorDot}
                  style={{ backgroundColor: row.color }}
                />
              ),
            },
          ]}
          isLoading={loading}
          options={{ filtering: true }}
          actions={[
            {
              icon: Info,
              tooltip: 'detail',
              onClick: (e, row) => {
                router.push({
                  pathname: routes.branches.detail,
                  query: { branchId: row.id },
                });
              },
            },
          ]}
        />
      </Paper>
    </>
  );
};

export default withPage(BranchesIndex, branchesBreadcrumbs, branchesResources);
