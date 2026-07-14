import {
  Avatar,
  CircularProgress,
  Container,
  CssBaseline,
  Link,
  makeStyles,
  TextField,
  Typography,
} from '@material-ui/core/';
import LockOutlinedIcon from '@material-ui/icons/LockOutlined';
import NextLink from 'next/link';
import { useForm } from 'react-hook-form';
import routes from '@shift-planner/shared/config/app/routes';
import { emailRegex } from '@shift-planner/shared/config/regexs';
import React from 'react';

import LoadingButton from 'components/LoadingButton';

import { LoginProps } from './types';

const useStyles = makeStyles(theme => ({
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

const Login = (props: LoginProps) => {
  const classes = useStyles();
  const { handleSubmit, register, errors } = useForm<{
    email: string;
    password: string;
  }>();

  const onSubmit = values => {
    props.onSubmit(values.email, values.password);
  };

  return (
    <Container maxWidth="xs">
      <CssBaseline />
      <div className={classes.paper}>
        <Avatar className={classes.avatar}>
          <LockOutlinedIcon />
        </Avatar>
        {props.loading ? (
          <CircularProgress size={46} className={classes.progress} />
        ) : null}

        <Typography component="h1" variant="h5">
          Přihlašování
        </Typography>
        <form
          onSubmit={handleSubmit(onSubmit)}
          className={classes.form}
          noValidate
        >
          <TextField
            inputRef={register({ required: true, pattern: emailRegex })}
            error={errors.email !== undefined || props.badInputs}
            variant="outlined"
            margin="normal"
            fullWidth
            label="Email"
            name="email"
            autoComplete="email"
          />
          <TextField
            inputRef={register({ required: true })}
            error={errors.password !== undefined || props.badInputs}
            variant="outlined"
            margin="normal"
            fullWidth
            name="password"
            label="Heslo"
            type="password"
            autoComplete="current-password"
          />
          <LoadingButton
            type="submit"
            fullWidth
            variant="contained"
            color="primary"
            className={classes.submit}
            loading={props.loading}
          >
            Přihlásit se
          </LoadingButton>
        </form>
        <NextLink href={routes.register}>
          <Link href={routes.register}>Registrovat se</Link>
        </NextLink>
      </div>
    </Container>
  );
};

export default Login;
