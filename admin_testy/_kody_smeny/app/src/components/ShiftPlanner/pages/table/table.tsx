import {
  Badge,
  Box,
  Button,
  IconButton,
  PropTypes,
  Table as TablePrefab,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Theme,
  Tooltip,
} from '@material-ui/core';
import { makeStyles } from '@material-ui/core/styles';
import AddIcon from '@material-ui/icons/AddCircle';
import CarIcon from '@material-ui/icons/DirectionsCar';
import EditIcon from '@material-ui/icons/Edit';
import React from 'react';

import { TableProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  cell: {
    whiteSpace: 'nowrap',
    paddingLeft: theme.spacing(1),
    paddingRight: theme.spacing(1),
  },
  header: {
    top: 0,
    position: 'sticky',
  },
}));

const Table = (props: TableProps) => {
  const classes = useStyles();

  const hours = [];

  const roles = props.shiftDay?.shiftRoles || [];
  roles.sort((r, r1) => {
    if (r.type.sortIndex > r1.type.sortIndex) {
      return 1;
    }
    if (r.type.sortIndex === r1.type.sortIndex) {
      return 0;
    }

    return -1;
  });

  for (let i = 0; i < 24; i++) {
    const hour = (i + props.dayStart) % 24;
    const nextHour = hour + 1 === 24 ? 0 : hour + 1;
    hours.push(
      <TableRow key={`hour${hour}`}>
        <TableCell>{`${hour} - ${nextHour}`}</TableCell>
        {roles.map(r => {
          const currentHour = r.shiftHours.find(h => h.startHour === hour);
          const employee = currentHour?.employee;

          let color: PropTypes.Color = 'secondary';
          let invertedColor: PropTypes.Color = 'primary';
          if (employee) {
            if (currentHour?.confirmed) {
              color = 'primary';
              invertedColor = 'secondary';
            } else {
              color = 'default';
            }
          }

          const button = (
            <Button
              color={color}
              variant="contained"
              onClick={() => props.onAssignClick(r.id, hour)}
              disabled={
                props.disabledAssigning ||
                !props.planableShiftRoleTypes.some(t => t === r.type.id)
              }
            >
              <Badge />
              <Box display="flex" justifyContent="center">
                {employee ? `${employee.name} ${employee.surname}` : 'Přiřadit'}
                {employee?.hasOwnCar && (
                  <Box pl={1}>
                    <CarIcon fontSize="small" />
                  </Box>
                )}
              </Box>
            </Button>
          );

          return (
            <TableCell
              key={`hour-role-${hour}-${r.id}`}
              padding="none"
              className={classes.cell}
              style={{ backgroundColor: r.type.color }}
            >
              {currentHour && (
                <>
                  {currentHour.isFirst && r.halfHour ? (
                    <Badge badgeContent="+30" color={invertedColor}>
                      {button}
                    </Badge>
                  ) : (
                    button
                  )}
                </>
              )}
            </TableCell>
          );
        })}
        <TableCell />
        <TableCell align="right">{`${hour} - ${nextHour}`}</TableCell>
      </TableRow>,
    );
  }

  const mappedHead = props.shiftDay?.shiftRoles.map(role => (
    <TableCell key={`head-${role.id}`} padding="none">
      <Tooltip title="Upravit slot" arrow>
        <IconButton
          color="primary"
          onClick={() => props.onRoleEdit(role.id)}
          disabled={props.disabledRoles}
        >
          <EditIcon />
        </IconButton>
      </Tooltip>
      {role.type.name}
    </TableCell>
  ));

  return (
    <div>
      <TablePrefab>
        <TableHead>
          <TableRow>
            <TableCell>Hodiny</TableCell>
            {mappedHead}
            <TableCell padding="none">
              <Tooltip title="Přidat slot" arrow>
                <IconButton
                  color="primary"
                  disabled={!props.shiftDay || props.disabledRoles}
                  onClick={props.onRoleAdd}
                >
                  <AddIcon />
                </IconButton>
              </Tooltip>
            </TableCell>
            <TableCell align="right">Hodiny</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>{hours}</TableBody>
      </TablePrefab>
    </div>
  );
};

export default Table;
