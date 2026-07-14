import { useQuery } from '@apollo/react-hooks';
import { Button, Divider, Grid, Theme, Typography } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import { gql } from 'apollo-boost';
import Link from 'next/link';
import resources from '@shift-planner/shared/config/api/resources';
import routes from '@shift-planner/shared/config/app/routes';
import dateFormat from 'dateformat';
import React from 'react';

import Paper from 'components/Paper';
import useResources from 'components/resources/useResources';
import withPage from 'components/withPage';

import profileBreadcrumbs from './breadcrumbs';
import Preferences from './preferences';
import { UserGetLogged } from './types';

const USER_GET_LOGGED = gql`
  {
    userGetLogged {
      id
      name
      surname
      email
      createTime
      lastLoginTime
      mainBranchName
      workingBranchNames
      shiftRoleTypeNames
      hasOwnCar
      phoneNumber
    }
  }
`;

const useStyles = makeStyles((theme: Theme) => ({
  actionContainer: {
    display: 'grid',
    justifyItems: 'right',
    paddingBottom: theme.spacing(1),
  },
  noWrapTypographyContainer: {
    display: 'flex',
  },
  noWrapTypography: {
    paddingRight: theme.spacing(1),
  },
}));

const ProfileIndex: React.FC = () => {
  const classes = useStyles();
  const { data, loading } = useQuery<UserGetLogged>(USER_GET_LOGGED, {
    fetchPolicy: 'no-cache',
  });
  const date = new Date(data?.userGetLogged.createTime || Date.now());
  const formattedDate = dateFormat(date, 'dd.mm.yyyy HH:MM:ss');
  const lastLogin = new Date(data?.userGetLogged.lastLoginTime || Date.now());
  const formattedLastLogin = dateFormat(lastLogin, 'dd.mm.yyyy HH:MM:ss');

  const isWorker = useResources([resources.preferredWeeks.see]);

  return (
    <>
      <Grid container spacing={2}>
        <Grid item xs={12} md={6}>
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <Paper title="Profil" loading={loading}>
                <Typography>
                  {`Jméno: ${data?.userGetLogged.name || ''}`}
                </Typography>
                <Typography>
                  {`Příjmení: ${data?.userGetLogged.surname || ''}`}
                </Typography>
                <Typography>
                  {`Email: ${data?.userGetLogged.email || ''}`}
                </Typography>
                <Typography>{`Datum registrace: ${formattedDate}`}</Typography>
                <Typography>{`Poslední přihlášení: ${formattedLastLogin}`}</Typography>
                <Typography>
                  {`Telefonní číslo: ${data?.userGetLogged.phoneNumber || ''}`}
                </Typography>
                <Typography>
                  {`Rozvozy vlastním autem: ${
                    data?.userGetLogged.hasOwnCar ? 'Ano' : 'Ne'
                  }`}
                </Typography>
              </Paper>
            </Grid>

            {isWorker && (
              <Grid item xs={12}>
                <Paper title="Detaily uživatele" loading={loading}>
                  <Typography variant="h6">Zařazen na pobočkách:</Typography>
                  <div className={classes.noWrapTypographyContainer}>
                    {data?.userGetLogged.workingBranchNames.map((b, index) => (
                      <Typography
                        key={`branch-${b}`}
                        className={classes.noWrapTypography}
                      >
                        {`${b}${
                          index !==
                          data?.userGetLogged.workingBranchNames.length - 1
                            ? ', '
                            : ''
                        } `}
                      </Typography>
                    ))}
                  </div>

                  <Typography variant="h6">Hlavní pobočka:</Typography>
                  <Typography>{data?.userGetLogged.mainBranchName}</Typography>

                  <Typography variant="h6">Pracovní pozice:</Typography>
                  <div className={classes.noWrapTypographyContainer}>
                    {data?.userGetLogged.shiftRoleTypeNames.map((s, index) => (
                      <Typography
                        key={`shiftRoleType-${s}`}
                        className={classes.noWrapTypography}
                      >
                        {`${s}${
                          index !==
                          data?.userGetLogged.shiftRoleTypeNames.length - 1
                            ? ', '
                            : ''
                        } `}
                      </Typography>
                    ))}
                  </div>
                </Paper>
              </Grid>
            )}
          </Grid>
        </Grid>
        <Grid item xs={12} md={6}>
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <Paper title="Akce" loading={loading}>
                <Typography variant="h6">Úprava profilu</Typography>
                <Typography>Upravte si telefonní číslo apod.</Typography>
                <div className={classes.actionContainer}>
                  <Link href={routes.profile.editProfile}>
                    <Button color="primary" variant="contained">
                      Upravit profil
                    </Button>
                  </Link>
                </div>
                <Divider />
                <Typography variant="h6">Změna hesla</Typography>
                <Typography>
                  Po této akci se vám změní přihlašovací heslo a již se nebudete
                  schopni přihlásit starým heslem.
                </Typography>
                <div className={classes.actionContainer}>
                  <Link href={routes.profile.changePassword}>
                    <Button color="primary" variant="contained">
                      Změnit heslo
                    </Button>
                  </Link>
                </div>
              </Paper>
            </Grid>
            <Grid item xs={12}>
              <Preferences loading={loading} />
            </Grid>
          </Grid>
        </Grid>
      </Grid>
    </>
  );
};

export default withPage(ProfileIndex, profileBreadcrumbs);
