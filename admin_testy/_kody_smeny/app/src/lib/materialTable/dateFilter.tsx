import { DatePicker } from '@material-ui/pickers';
import { Column } from 'material-table';
import React, { useState } from 'react';

const DateFilter: React.FC<{
  onFilterChanged: (rowId: string, value: any) => void;
  columnDef: Column<any>;
}> = props => {
  const [value, setValue] = useState<undefined | Date>(undefined);

  return (
    <DatePicker
      value={value}
      labelFunc={label => (value ? label.format('DD.MM. YYYY') : '')}
      onChange={e => {
        setValue(e.toDate());
        props.onFilterChanged(
          // @ts-ignore
          props.columnDef.tableData.id,
          e,
        );
      }}
    />
  );
};

export default DateFilter;
