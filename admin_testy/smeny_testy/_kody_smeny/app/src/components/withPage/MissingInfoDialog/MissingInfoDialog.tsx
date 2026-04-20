import {
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Divider,
  FormControlLabel,
  TextField,
} from '@material-ui/core';
import { phoneRegex } from '@shift-planner/shared/config/regexs';
import React, { useEffect, useState } from 'react';

import MissingInfoDialogProps from './types';

const MissingInfoDialog = (props: MissingInfoDialogProps) => {
  const carIsEmpty =
    props.car === null && props.shiftRoleTypes.some(type => type.useCars);
  const phoneIsEmpty = props.phone === null;

  const [ownCar, setOwnCar] = useState<boolean | null>(null);
  const [phoneNumber, setPhone] = useState<string | null>(null);

  const phoneInvalid = phoneIsEmpty && !phoneRegex.test(phoneNumber);

  useEffect(() => {
    if (carIsEmpty) {
      setOwnCar(false);
    }
  }, [props.car]);

  return (
    <Dialog open={!props.loading && (carIsEmpty || phoneIsEmpty)}>
      <DialogTitle>Ve vašem profilu chybí některé informace</DialogTitle>
      <DialogContent>
        <DialogContentText>
          Prosím doplňte je nyní. Informace můžete vždy upravit v profilu
        </DialogContentText>
        <Divider />
        {phoneIsEmpty && (
          <>
            <Box pb={1} pt={1}>
              <TextField
                label="Telefonní číslo"
                variant="outlined"
                value={phoneNumber}
                onChange={e => setPhone(e.target.value)}
                error={phoneInvalid}
              />
            </Box>
            <Divider />
          </>
        )}
        {carIsEmpty && (
          <>
            <Box pb={1} pt={1}>
              <FormControlLabel
                label="Rozvozy vlastním autem"
                // prettier-ignore
                control={(
                  <Checkbox
                    color="primary"
                    checked={ownCar}
                    onChange={(e) => setOwnCar(e.target.checked)}
                  />
              )}
              />
            </Box>
            <Divider />
          </>
        )}
      </DialogContent>
      <DialogActions>
        <Button
          color="primary"
          onClick={() => props.onSubmit({ car: ownCar, phone: phoneNumber })}
          disabled={phoneInvalid}
        >
          Uložit
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default MissingInfoDialog;
