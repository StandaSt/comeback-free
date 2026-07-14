import {
  Box,
  makeStyles,
  Paper as PaperPrefab,
  Theme,
  Typography,
} from '@material-ui/core';
import React from 'react';

import OverlayLoading from 'components/OverlayLoading';
import OverlayLoadingContainer from 'components/OverlayLoading/OverlayLoadingContainer';

import { PaperProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  paper: {
    padding: theme.spacing(2),
  },
  actions: {
    display: 'flex',
    justifyContent: 'flex-end',
  },
  actionsInnerWrapper: {
    display: 'flex',
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
}));

const Paper = (props: PaperProps) => {
  const classes = useStyles();

  const { title, children, loading, actions, footer, ...rest } = props;

  return (
    <OverlayLoadingContainer>
      <OverlayLoading loading={loading} />
      <PaperPrefab className={classes.paper} elevation={2} {...rest}>
        {title && (
          <Typography variant="h5" component="h2">
            {title}
          </Typography>
        )}
        {children}
        {(actions || footer) && (
          <div className={classes.footer}>
            <div className={classes.footerText}>{footer}</div>
            <Box
              display={props.actionsFullWidth ? '' : 'flex'}
              justifyContent="flex-end"
            >
              <div className={classes.actions}>
                {actions &&
                  actions.map((a, index) => (
                    <Box
                      // eslint-disable-next-line react/no-array-index-key
                      key={index}
                      width="100%"
                      display="flex"
                      justifyContent="flex-end"
                      marginLeft={index === 0 ? 0 : 2}
                    >
                      {a}
                    </Box>
                  ))}
              </div>
            </Box>
          </div>
        )}
      </PaperPrefab>
    </OverlayLoadingContainer>
  );
};

export default Paper;
