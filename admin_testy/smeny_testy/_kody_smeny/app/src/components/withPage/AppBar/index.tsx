import {
  Avatar,
  Box,
  Hidden,
  IconButton,
  makeStyles,
  Theme,
  Toolbar,
  Tooltip,
  Typography,
} from '@material-ui/core';
import LogoutIcon from '@material-ui/icons/ExitToApp';
import MenuIcon from '@material-ui/icons/Menu';
import dynamic from 'next/dynamic';
import Link from 'next/link';
import { useCookies } from 'react-cookie';
import appConfig from '@shift-planner/shared/config/app';
import routes from '@shift-planner/shared/config/app/routes';
import React from 'react';

import { AppBarProps } from './types';

const AppBarPrefab = dynamic(import('@material-ui/core/AppBar'), {
  ssr: false,
});

const useStyles = makeStyles((theme: Theme) => ({
  appBar: {
    zIndex: `${theme.zIndex.drawer + 1} !important` as any,
  },
  toolbar: {
    display: 'grid',
    gridGap: theme.spacing(1),
    [theme.breakpoints.up('md')]: {
      gridTemplateColumns: '1fr auto',
    },
    [theme.breakpoints.down('md')]: {
      gridTemplateColumns: 'auto 1fr auto',
    },
  },
  rightIcons: {
    justifySelf: 'end',
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    alignItems: 'center',
  },
  avatar: {
    color: theme.palette.getContrastText(theme.palette.secondary.main),
    backgroundColor: theme.palette.secondary.main,
  },
  link: {
    textDecoration: 'none',
  },
  appBarRootDark: {
    backgroundColor: `${theme.palette.background.paper} !important`,
  },
  white: {
    color: theme.palette.text.primary,
  },
}));

const AppBar = (props: AppBarProps) => {
  const classes = useStyles();

  const [cookies] = useCookies();
  const darkMode = cookies[appConfig.cookies.darkTheme] === 'true';

  const evaluation = props.user?.totalEvaluationScore;

  return (
    <AppBarPrefab
      position="fixed"
      classes={darkMode ? { root: classes.appBarRootDark } : {}}
      className={classes.appBar}
      style={{ backgroundColor: appConfig.devNavBar ? '#ffaa00' : '' }}
    >
      <Toolbar className={classes.toolbar}>
        <Hidden lgUp>
          <IconButton
            className={darkMode ? classes.white : ''}
            onClick={props.drawerOpen}
            color="inherit"
            edge="start"
          >
            <MenuIcon />
          </IconButton>
        </Hidden>
        <Typography
          className={darkMode ? classes.white : ''}
          variant="h6"
          component="h1"
          noWrap
        >
          {appConfig.appName}
        </Typography>
        <Box display="flex" alignItems="center">
          <Box pr={2}>
            <Link href={routes.myEvaluation.index}>
              <a className={classes.link}>
                <Tooltip title="Hodnocení">
                  <Avatar className={classes.avatar}>
                    {evaluation > 0 ? `+${evaluation}` : evaluation}
                  </Avatar>
                </Tooltip>
              </a>
            </Link>
          </Box>
          <Box pr={2}>
            <Link href={routes.profile.index}>
              <a className={classes.link}>
                <Tooltip title="Profil">
                  <Avatar className={classes.avatar} color="secondary">
                    {`${props?.user?.name[0] || ''}${
                      props?.user?.surname[0] || ''
                    }`}
                  </Avatar>
                </Tooltip>
              </a>
            </Link>
          </Box>
          <Tooltip title="Odhlásit se">
            <IconButton
              className={darkMode ? classes.white : ''}
              color="inherit"
              onClick={props.onLogOut}
            >
              <LogoutIcon />
            </IconButton>
          </Tooltip>
        </Box>
      </Toolbar>
    </AppBarPrefab>
  );
};

export default AppBar;
