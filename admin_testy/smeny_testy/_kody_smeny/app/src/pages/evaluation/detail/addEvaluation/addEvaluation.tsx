import React, { useState } from 'react';
import {
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
  TextField,
  Theme,
  Typography,
} from '@material-ui/core';
import RemoveCircleIcon from '@material-ui/icons/RemoveCircle';
import AddCircleIcon from '@material-ui/icons/AddCircle';
import { makeStyles } from '@material-ui/core/styles';

import LoadingButton from 'components/LoadingButton';

import { AddEvaluationProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  positive: {
    color: theme.palette.success.main,
  },
  negative: {
    color: theme.palette.error.main,
  },
}));

const AddEvaluation: React.FC<AddEvaluationProps> = props => {
  const classes = useStyles();
  const [positive, setPositive] = useState<boolean | undefined>(undefined);
  const [description, setDescription] = useState('');
  const [modal, setModal] = useState(false);
  const [error, setError] = useState('');

  const modalOpenHandler = (): void => {
    if (description.length < 10) {
      setError('description');
    } else {
      setError('');
      setModal(true);
    }
  };

  const submitHandler = (): void => {
    props.onSubmit(Boolean(positive), description).then(() => {
      setModal(false);
      setPositive(undefined);
      setDescription('');
    });
  };

  return (
    <>
      <Box pt={2}>
        <Grid container spacing={2}>
          <Grid item xs={12}>
            <TextField
              error={error === 'description'}
              variant="outlined"
              multiline
              rows={4}
              label="Zdůvodnění (min. 10 znaků)"
              value={description}
              fullWidth
              onChange={e => {
                setDescription(e.target.value);
              }}
            />
          </Grid>
          <Grid item xs={6}>
            <Button className={classes.positive}>
              <AddCircleIcon
                fontSize="large"
                onClick={() => {
                  setPositive(true);
                  modalOpenHandler();
                }}
              />
            </Button>
          </Grid>
          <Grid item xs={6} container justify="flex-end">
            <Button
              color="secondary"
              className={classes.negative}
              onClick={() => {
                setPositive(false);
                modalOpenHandler();
              }}
            >
              <RemoveCircleIcon fontSize="large" />
            </Button>
          </Grid>
        </Grid>
      </Box>
      <Dialog open={modal}>
        <DialogTitle>Opravdu chcete uložit toto hodnocení?</DialogTitle>
        <DialogContent>
          <Typography>{positive ? 'Pozitivní' : 'Negativní'}</Typography>
          <Typography>{description}</Typography>
        </DialogContent>
        <DialogActions>
          <LoadingButton
            color="primary"
            onClick={submitHandler}
            loading={props.loading}
          >
            Uložit
          </LoadingButton>
          <LoadingButton
            color="secondary"
            onClick={() => setModal(false)}
            loading={props.loading}
          >
            Zrušit
          </LoadingButton>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default AddEvaluation;
