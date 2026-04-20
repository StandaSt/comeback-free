import { Table, TableBody, TableHead } from '@material-ui/core';
import React from 'react';

import SimpleRow from 'components/table/SimpeRow';

import { SimpleTableProps } from './types';

const SimpleTable: React.FC<SimpleTableProps> = props => (
  <Table>
    <TableHead>
      <SimpleRow name={props.customFirstColumnName || 'Informace'}>
        {props.customSecondColumnName || 'Hodnota'}
      </SimpleRow>
    </TableHead>
    <TableBody>{props.children}</TableBody>
  </Table>
);

export default SimpleTable;
