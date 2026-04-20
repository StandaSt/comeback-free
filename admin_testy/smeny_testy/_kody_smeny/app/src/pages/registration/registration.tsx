import {
  Avatar,
  CircularProgress,
  Container,
  makeStyles,
  TextField,
  Theme,
  Typography,
} from '@material-ui/core';
import LockOutlinedIcon from '@material-ui/icons/LockOutlined';
import { useForm } from 'react-hook-form';
import { emailRegex } from '@shift-planner/shared/config/regexs';
import React from 'react';

import LoadingButton from 'components/LoadingButton';

import { FormTypes, RegistrationProps } from './types';

const useStyles = makeStyles((theme: Theme) => ({
  paper: {
    marginTop: theme.spacing(8),
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
  },
  avatar: {
    margin: theme.spacing(1),
    backgroundColor: theme.palette.secondary.main,
  },
  form: {
    width: '100%',
    marginTop: theme.spacing(1),
  },
  submit: {
    margin: theme.spacing(3, 0, 2),
  },
  progress: {
    position: 'absolute',
    marginTop: 5,
  },
}));

const Registration = (props: RegistrationProps) => {
  const classes = useStyles();

  const { register, errors, handleSubmit, setError } = useForm<FormTypes>();

  const submitHandler = (values: FormTypes) => {
    if (values.password1 !== values.password2) {
      setError('password1', 'notMatch');
      setError('password2', 'notMatch');
    } else props.onSubmit(values);
  };

  return (
    <Container maxWidth="xs">
      <div className={classes.paper}>
        <Avatar className={classes.avatar}>
          <LockOutlinedIcon />
        </Avatar>
        {props.loading ? (
          <CircularProgress size={46} className={classes.progress} />
        ) : null}
        <Typography component="h1" variant="h5">
          Registrace
        </Typography>
        <form className={classes.form} onSubmit={handleSubmit(submitHandler)}>
          <TextField
            variant="outlined"
            label="Email - používá se pro přihlášení"
            fullWidth
            margin="normal"
            name="email"
            inputRef={register({ required: true, pattern: emailRegex })}
            error={errors.email !== undefined}
          />
          <TextField
            variant="outlined"
            label="Jméno"
            fullWidth
            margin="normal"
            name="name"
            inputRef={register({ required: true })}
            error={errors.name !== undefined}
          />
          <TextField
            variant="outlined"
            label="Příjmení"
            fullWidth
            margin="normal"
            name="surname"
            inputRef={register({ required: true })}
            error={errors.surname !== undefined}
          />
          <TextField
            variant="outlined"
            label="Heslo"
            fullWidth
            margin="normal"
            type="password"
            name="password1"
            inputRef={register({ required: true })}
            error={errors.password1 !== undefined}
          />
          <TextField
            variant="outlined"
            label="Heslo znovu"
            fullWidth
            margin="normal"
            type="password"
            name="password2"
            inputRef={register({ required: true })}
            error={errors.password2 !== undefined}
          />
          <LoadingButton
            type="submit"
            fullWidth
            variant="contained"
            color="primary"
            className={classes.submit}
            loading={props.loading}
          >
            Registrovat se
          </LoadingButton>
        </form>
      </div>
    </Container>
  );
};

export default Registration;
