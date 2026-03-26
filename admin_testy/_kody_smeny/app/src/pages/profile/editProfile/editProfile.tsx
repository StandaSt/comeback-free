import {
  Box,
  Button,
  Checkbox,
  FormControlLabel,
  TextField,
} from '@material-ui/core';
import Link from 'next/link';
import routes from '@shift-planner/shared/config/app/routes';
import React, { useEffect, useState } from 'react';

import Paper from 'components/Paper';

import { phoneRegex } from '../../../../../shared/config/regexs';

import { EditProfileProps } from './types';

const EditProfile = (props: EditProfileProps) => {
  const [hasOwnCar, setHasOwnCar] = useState(false);
  const [phoneNumber, setPhoneNumber] = useState('');

  useEffect(() => {
    setHasOwnCar(props.hasOwnCar);
    setPhoneNumber(props.phoneNumber);
  }, [props.hasOwnCar, props.phoneNumber]);

  const phoneInvalid = !phoneRegex.test(phoneNumber);

  return (
    <Paper
      title="Úprava profilu"
      loading={props.loading}
      actions={[
        <Button
          key={0}
          color="primary"
          variant="contained"
          onClick={() => props.onSubmit({ hasOwnCar, phoneNumber })}
          disabled={phoneInvalid}
        >
          Uložit
        </Button>,
        <Link key={1} href={routes.profile.index} passHref>
          <Button color="secondary" variant="contained">
            Zpět
          </Button>
        </Link>,
      ]}
    >
      <Box pt={2} pb={2}>
        <TextField
          label="Telefonní číslo"
          variant="outlined"
          value={phoneNumber}
          onChange={e => setPhoneNumber(e.target.value)}
          error={phoneInvalid}
        />
      </Box>
      <FormControlLabel
        // prettier-ignore
        control={(
          <Checkbox
            checked={hasOwnCar}
            color="primary"
            onChange={(e) => setHasOwnCar(e.target.checked)}
          />
        )}
        label="Rozvozy vlastním autem"
      />
    </Paper>
  );
};

export default EditProfile;
