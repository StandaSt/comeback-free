import MaterialTablePrefab, { MaterialTableProps } from 'material-table';
import React, { useEffect, useState } from 'react';

import materialTableIcons from 'lib/materialTable/icons';

const MaterialTable: React.FC<MaterialTableProps<any>> = props => {
  const [columns, setColumns] = useState(props.columns);
  const [oldData, setOldData] = useState<any[]>([]);
  // Totally gross hack for fucking material table to update data but not clear filters
  useEffect(() => {
    let update = false;
    columns.forEach((column, index) => {
      const propColumn = props.columns[index];
      if (
        propColumn.title !== column.title ||
        propColumn.field !== column.field ||
        JSON.stringify(propColumn.lookup) !== JSON.stringify(column.lookup)
      ) {
        update = true;
      } else if (
        typeof props.data === 'object' &&
        JSON.stringify(props.data) !== JSON.stringify(oldData)
      ) {
        update = true;
      }
    });
    if (update) {
      setColumns(props.columns);
      if (typeof props.data === 'object') setOldData(props.data);
    }
  }, [props.columns, props.data]);

  return (
    <MaterialTablePrefab
      {...props}
      columns={columns}
      icons={materialTableIcons}
      components={{
        Container: p => p.children,
        ...props.components,
      }}
      options={{
        toolbar: false,
        pageSizeOptions: [250, 500, 1000],
        pageSize: 250,
        headerStyle: {
          backgroundColor: 'inherit',
        },
        emptyRowsWhenPaging: false,
        ...props.options,
      }}
      localization={{
        pagination: {
          nextTooltip: 'Další stránka',
          previousTooltip: 'Předchozí stránka',
          lastTooltip: 'Poslední stránka',
          firstTooltip: 'První stránka',
          labelRowsSelect: 'řádků',
          labelDisplayedRows: '{from}-{to} z {count}',
        },
        header: {
          actions: 'Akce',
        },
        body: {
          emptyDataSourceMessage: 'Žádná data k zobrazení',
        },
      }}
    />
  );
};

export default MaterialTable;
