import { Breadcrumbs, Link as MLink, Theme } from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import Link from 'next/link';
import React from 'react';

import { ContentProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  content: {
    flexGrow: 1,
    padding: theme.spacing(3),
  },
  toolbar: theme.mixins.toolbar,
}));

const Content: React.FC<ContentProps> = props => {
  const classes = useStyles();

  return (
    <div className={classes.content}>
      <div className={classes.toolbar} />
      <Breadcrumbs>
        {props.breadcrumbs.map(b => (
          <div key={b.link + b.label}>
            {b.link && (
              <Link href={b.link}>
                <MLink href="#" color="inherit">
                  {b.label}
                </MLink>
              </Link>
            )}
            {!b.link && (
              <MLink href="#" color="inherit">
                {b.label}
              </MLink>
            )}
          </div>
        ))}
      </Breadcrumbs>
      {props.children}
    </div>
  );
};

export default Content;
