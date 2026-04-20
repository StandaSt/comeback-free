import { makeStyles, TableCell, TableRow, Tooltip } from '@material-ui/core';
import React from 'react';

const useStyles = makeStyles({
  fitWidth: {
    display: 'table',
  },
  noWrap: {
    whiteSpace: 'nowrap',
  },
});

const SimpleRow: React.FC<{ name: string; tooltip?: string }> = props => {
  const classes = useStyles();

  const name = <div className={classes.fitWidth}>{props.name}</div>;

  return (
    <TableRow>
      <TableCell style={{ verticalAlign: 'top' }}>
        {props.tooltip ? (
          <Tooltip arrow title={props.tooltip}>
            {name}
          </Tooltip>
        ) : (
          name
        )}
      </TableCell>
      <TableCell padding="none">
        <div className={classes.noWrap}>{props.children}</div>
      </TableCell>
    </TableRow>
  );
};

export default SimpleRow;
