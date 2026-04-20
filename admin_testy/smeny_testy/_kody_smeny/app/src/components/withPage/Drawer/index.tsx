import {
  Drawer as DrawerPrefab,
  Hidden,
  makeStyles,
  SwipeableDrawer,
  Theme,
} from '@material-ui/core';
import React from 'react';

import List from './List';
import { DrawerProps } from './types';

const drawerWidth = 300;

const useStyles = makeStyles((theme: Theme) => ({
  root: {
    display: 'flex',
  },
  drawer: {
    width: drawerWidth,
    flexShrink: 0,
  },
  drawerPaper: {
    width: drawerWidth,
  },
  toolbar: theme.mixins.toolbar,
}));

const Drawer = (props: DrawerProps) => {
  const classes = useStyles();

  return (
    <>
      <Hidden mdDown>
        <DrawerPrefab
          className={classes.drawer}
          variant="permanent"
          classes={{
            paper: classes.drawerPaper,
          }}
        >
          <div className={classes.toolbar} />
          <List />
        </DrawerPrefab>
      </Hidden>
      <Hidden lgUp>
        <SwipeableDrawer
          classes={{
            paper: classes.drawerPaper,
          }}
          onClose={() => {
            props.setOpen(false);
          }}
          onOpen={() => {
            props.setOpen(true);
          }}
          open={props.open}
        >
          <List />
        </SwipeableDrawer>
      </Hidden>
    </>
  );
};
export default Drawer;
