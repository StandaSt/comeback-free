import {
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  Grid,
  InputLabel,
  MenuItem,
  Select,
  TextField,
} from '@material-ui/core';
import { useForm } from 'react-hook-form';
import React, { useState } from 'react';

import LoadingButton from 'components/LoadingButton';

import { AddModalFormValues, AddModalProps } from './types';

const AddModal: React.FC<AddModalProps> = props => {
  const { register, handleSubmit } = useForm<AddModalFormValues>();
  const [branch, setBranch] = useState(props.defaultBranch || -1);

  const mappedSelect = props.userBranches?.map(b => (
    <MenuItem key={b.id} value={b.id}>
      {b.name}
    </MenuItem>
  ));

  const submitHandler = (values: AddModalFormValues): void => {
    if (branch >= 0) props.onSubmit({ ...values, branchId: branch });
  };

  return (
    <Dialog open={props.open || false}>
      <DialogTitle>
        {`${props.editing ? 'Úprava' : 'Vytvoření'} šablony`}
      </DialogTitle>
      <form onSubmit={handleSubmit(submitHandler)}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <TextField
                variant="outlined"
                label="Název"
                name="name"
                defaultValue={props.defaultName}
                fullWidth
                inputRef={register({ required: true })}
              />
            </Grid>
            <Grid item xs={12}>
              <FormControl variant="outlined" fullWidth>
                <InputLabel id="branchLabel">Pobočka</InputLabel>
                <Select
                  labelId="branchLabel"
                  label="Pobočka"
                  name="branch"
                  value={branch}
                  onChange={e => setBranch(+e.target.value)}
                >
                  {mappedSelect}
                </Select>
              </FormControl>
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <LoadingButton color="primary" type="submit" loading={props.loading}>
            Uložit
          </LoadingButton>
          <LoadingButton
            color="secondary"
            onClick={props.close}
            loading={props.loading}
          >
            Zrušit
          </LoadingButton>
        </DialogActions>
      </form>
    </Dialog>
  );
};

export default AddModal;
