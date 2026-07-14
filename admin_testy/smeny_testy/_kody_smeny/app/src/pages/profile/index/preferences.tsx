import { useMutation } from '@apollo/react-hooks';
import { FormControlLabel } from '@material-ui/core';
import { gql } from 'apollo-boost';
import dynamic from 'next/dynamic';
import { useCookies } from 'react-cookie';
import appConfig from '@shift-planner/shared/config/app';
import React from 'react';

import Paper from 'components/Paper';

import { PreferencesProps } from './types';

const Switch = dynamic(import('@material-ui/core/Switch'), { ssr: false });

const USER_CHANGE_DARK_THEME = gql`
  mutation($darkTheme: Boolean!) {
    userChangeDarkTheme(darkTheme: $darkTheme) {
      id
      darkTheme
    }
  }
`;

const Preferences = (props: PreferencesProps) => {
  const [cookies] = useCookies();
  const [userChangeDarkTheme, { loading }] = useMutation(
    USER_CHANGE_DARK_THEME,
  );
  const checked = cookies[appConfig.cookies.darkTheme] === 'true';

  const changeHandler = () => {
    userChangeDarkTheme({ variables: { darkTheme: !checked } });
  };

  return (
    <Paper title="Preference" loading={props.loading}>
      <FormControlLabel
        control={(
          <Switch
            disabled={loading}
            checked={checked}
            onChange={changeHandler}
          />
        )}
        label="Tmavý režim"
      />
    </Paper>
  );
};

export default Preferences;
