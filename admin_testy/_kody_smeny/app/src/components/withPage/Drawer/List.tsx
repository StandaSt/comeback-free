import {
  Collapse,
  Divider,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  makeStyles,
  Theme,
} from '@material-ui/core';
import ExpandLess from '@material-ui/icons/ExpandLess';
import ExpandMore from '@material-ui/icons/ExpandMore';
import Link from 'next/link';
import { useRouter } from 'next/router';
import React, { useState } from 'react';

import useResources from 'components/resources/useResources';

import listConfig, { ListConfig } from './listConfig';

const useStyles = makeStyles((theme: Theme) => ({
  link: {
    textDecoration: 'none',
    color: 'inherit',
  },
  subList: {
    paddingLeft: theme.spacing(6),
  },
}));

const DrawerList = () => {
  const classes = useStyles();
  const router = useRouter();

  const Item = ({
    link,
    icon,
    label,
    subList,
    resources,
    padding = false,
  }: {
    link?: string;
    icon: JSX.Element;
    label: string;
    subList?: ListConfig[];
    resources?: string[];
    padding?: boolean;
  }) => {
    const hasAccess = useResources(resources || []);
    const [open, setOpen] = useState(
      subList ? subList.some(s => router.pathname.startsWith(s.link)) : false,
    );
    const withoutLink = (
      <>
        {subList && <Divider />}
        <ListItem
          button
          selected={router.pathname.startsWith(link)}
          onClick={() => setOpen(!open)}
          className={padding ? classes.subList : ''}
        >
          <ListItemIcon>{icon}</ListItemIcon>
          <ListItemText primary={label} />
          {subList && <>{open ? <ExpandLess /> : <ExpandMore />}</>}
        </ListItem>
        {subList && (
          <Collapse in={open}>
            {subList.map(s => (
              <Item key={`subItem${s.label}-${label}-${link}`} padding {...s} />
            ))}
          </Collapse>
        )}
      </>
    );
    const withLink = link ? (
      <Link href={link}>
        <a href={link} className={classes.link}>
          {withoutLink}
        </a>
      </Link>
    ) : null;

    return <>{hasAccess && (link ? withLink : withoutLink)}</>;
  };

  return (
    <List>
      {listConfig.map(item => (
        <Item key={`item${item.label}-${item.link}`} {...item} />
      ))}
    </List>
  );
};

export default DrawerList;
