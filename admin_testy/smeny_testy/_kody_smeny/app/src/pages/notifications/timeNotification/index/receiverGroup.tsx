import React from 'react';
import {
  Box,
  Card,
  CardContent,
  fade,
  IconButton,
  Theme,
  Typography,
} from '@material-ui/core';
import EditIcon from '@material-ui/icons/Edit';
import DeleteIcon from '@material-ui/icons/Delete';
import AddIcon from '@material-ui/icons/Add';
import { makeStyles } from '@material-ui/core/styles';
import Link from 'next/link';
import { useRouter } from 'next/router';
import routes from '@shift-planner/shared/config/app/routes';

import { ReceiverGroupProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  and: {
    padding: theme.spacing(1),
  },
  values: {
    backgroundColor: fade(theme.palette.common.black, 0.1),
    whiteSpace: 'nowrap',
    borderRadius: theme.spacing(1),
    padding: theme.spacing(0.5),
    marginLeft: theme.spacing(0.5),
  },
  cardContent: {
    padding: theme.spacing(2),
    '&:last-child': {
      padding: theme.spacing(2),
    },
  },
  link: {
    color: 'inherit',
    textDecoration: 'none',
  },
}));

const ReceiverGroup: React.FC<ReceiverGroupProps> = props => {
  const classes = useStyles();
  const router = useRouter();

  const mappedReceivers = props.receivers?.map(receiver => {
    let label = '?';
    let value = '?';
    let url: any = '';

    if (receiver.resource) {
      label = 'Pravomoc';
      value = `${receiver.resource.category.label}: ${receiver.resource.label}`;
      url = {
        pathname: routes.roles.resourceDetail,
        query: { resourceId: receiver.resource.id },
      };
    }
    if (receiver.role) {
      label = 'Role';
      value = receiver.role.name;
      url = {
        pathname: routes.roles.roleDetail,
        query: { roleId: receiver.role.id },
      };
    }

    return (
      <Box key={receiver.id} display="flex" alignItems="center">
        <Link
          href={{
            pathname: routes.notifications.timeNotification.editReceiver,
            query: {
              receiverId: receiver.id,
              timeNotificationId: router.query.id,
            },
          }}
        >
          <IconButton color="primary" size="small">
            <EditIcon fontSize="inherit" />
          </IconButton>
        </Link>
        <IconButton color="secondary" size="small">
          <DeleteIcon fontSize="inherit" />
        </IconButton>
        <Typography>{`${label}:`}</Typography>
        <Link href={url} passHref>
          {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
          <a className={classes.link}>
            <Typography className={classes.values}>{value}</Typography>
          </a>
        </Link>
        <Typography className={classes.and}>
          <Box fontWeight="bold">A</Box>
        </Typography>
      </Box>
    );
  });

  return (
    <Card variant="outlined">
      <CardContent classes={{ root: classes.cardContent }}>
        <Box display="flex" alignItems="center" flexWrap="wrap">
          {mappedReceivers}

          <IconButton
            color="primary"
            size="small"
            onClick={props.onReceiverAdd}
          >
            <AddIcon />
          </IconButton>
        </Box>
      </CardContent>
    </Card>
  );
};

export default ReceiverGroup;
