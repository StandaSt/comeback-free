import { Button, Theme, Typography } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import { useRouter } from 'next/router';
import { useCookies } from 'react-cookie';
import appConfig from '@shift-planner/shared/config/app';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

const useStyles = makeStyles((theme: Theme) => ({
  center: {
    display: 'grid',
    alignItems: 'center',
    height: '100vh',
    justifyItems: 'center',
  },
  container: {
    display: 'grid',
    justifyItems: 'center',
    gridGap: theme.spacing(2),
    gridTemplateColumns: '1fr 1fr 1fr',
    gridTemplateAreas:
      '"title title title" "description description description" "button1 button2 button3"',
    gridTemplateRows: '1fr 1fr 1fr',
  },
  itemContainer: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    justifyItems: 'center',
    width: '100%',
  },
  fitWidth: {
    width: 'fit-content',
  },
}));

const NoAccess = () => {
  const classes = useStyles();

  const router = useRouter();
  const [, , removeCookies] = useCookies();

  const loginHandler = (): void => {
    removeCookies(appConfig.cookies.token);
    router.push({
      pathname: routes.login,
      query: {
        redirectURL: router.pathname,
        redirectQuery: JSON.stringify(router.query),
      },
    });
  };

  return (
    <div className={classes.center}>
      <div className={classes.fitWidth}>
        <div className={classes.container}>
          <div style={{ gridArea: 'title' }}>
            <Typography align="center" variant="h4">
              Na tuto stránku nemáte přístup
            </Typography>
          </div>
          <div style={{ gridArea: 'description' }}>
            <Typography variant="h5" align="center">
              Vaše přihlášení po 4 hodinách vypršelo nebo nemáte dostatečná
              práva nebo jste ztratili připojení k internetu
            </Typography>
          </div>
          <div>
            <Button onClick={loginHandler} variant="contained" color="primary">
              Přihlásit se
            </Button>
          </div>
          <div>
            <Button onClick={router.back} variant="contained" color="secondary">
              Vratit se zpět
            </Button>
          </div>
          <div>
            <Button
              onClick={() => router.push(routes.dashboard)}
              variant="contained"
              color="primary"
            >
              Přejít na Přehled
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default NoAccess;
