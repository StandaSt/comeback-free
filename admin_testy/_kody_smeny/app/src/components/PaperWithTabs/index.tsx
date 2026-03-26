import {
  makeStyles,
  Paper as PaperPrefab,
  Tab,
  Tabs,
  Theme,
  Typography,
} from '@material-ui/core';
import React, { useState } from 'react';

import OverlayLoading from 'components/OverlayLoading';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';
import { PaperWithTabsProps } from 'components/PaperWithTabs/types';

const tabHeight = '30px';
const useStyles = makeStyles((theme: Theme) => ({
  paper: {
    padding: theme.spacing(2),
  },

  actions: {
    display: 'grid',
    justifyItems: 'right',
  },
  actionsInnerWrapper: {
    display: 'flex',
  },
  action: {
    marginLeft: theme.spacing(2),
  },
  footer: {
    marginTop: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: 'auto 1fr',
  },
  footerText: {
    display: 'grid',
    alignItems: 'center',
  },
  head: {
    display: 'grid',
    gridTemplateColumns: 'auto 1fr',
    gridGap: theme.spacing(2),
  },
  tabsRoot: {
    minHeight: tabHeight,
    height: tabHeight,
  },
  tabRoot: {
    minHeight: tabHeight,
    height: tabHeight,
    minWidth: 100,
  },
}));

const PaperWithTabs = (props: PaperWithTabsProps) => {
  const classes = useStyles();
  const [value, setValue] = useState(0);

  const { title, children, loading, actions, footer, tabs, ...rest } = props;

  const mappedTabs = props.tabs.map(tab => (
    <Tab
      disabled={tab.disabled}
      key={`tab${tab.label}`}
      label={tab.label}
      classes={{
        root: classes.tabRoot,
      }}
    />
  ));

  const mappedPanels = tabs.map((tab, index) => (
    <div key={`tabPanel${tab.label}`}>
      {index === value && <>{tab.panel}</>}
    </div>
  ));

  return (
    <OverlayLoadingContainer>
      <PaperPrefab className={classes.paper} elevation={2} {...rest}>
        <div className={classes.head}>
          {title && (
            <Typography variant="h5" component="h2">
              {title}
            </Typography>
          )}
          <div>
            <Tabs
              value={value}
              onChange={(e, v) => setValue(v)}
              classes={{
                root: classes.tabsRoot,
              }}
              variant="scrollable"
              scrollButtons="auto"
            >
              {mappedTabs}
            </Tabs>
          </div>
        </div>
        {mappedPanels}
        {children}
        {(actions || footer) && (
          <div className={classes.footer}>
            <div className={classes.footerText}>{footer}</div>
            <div className={classes.actions}>
              <div className={classes.actionsInnerWrapper}>
                {actions &&
                  actions.map((a, index) => (
                    // eslint-disable-next-line react/no-array-index-key
                    <div key={index} className={classes.action}>
                      {a}
                    </div>
                  ))}
              </div>
            </div>
          </div>
        )}
      </PaperPrefab>
      <OverlayLoading loading={loading}> </OverlayLoading>
    </OverlayLoadingContainer>
  );
};

export default PaperWithTabs;
