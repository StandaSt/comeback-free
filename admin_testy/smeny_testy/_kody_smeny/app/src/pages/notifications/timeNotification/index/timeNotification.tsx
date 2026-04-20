import React, { useEffect, useState } from 'react';
import {
  TextField,
  Typography,
  Box,
  Card,
  CardContent,
  IconButton,
  Theme,
  fade,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Grid,
  Button,
  Divider,
} from '@material-ui/core';
import EditIcon from '@material-ui/icons/Edit';
import DeleteIcon from '@material-ui/icons/Delete';
import { makeStyles } from '@material-ui/core/styles';
import AddIcon from '@material-ui/icons/Add';
import { DateTimePicker } from '@material-ui/pickers';
import dayjs from 'dayjs';

import Paper from 'components/Paper';

import { Repeat, TimeNotificationProps, UpdateValues } from './types';
import ReceiverGroup from './receiverGroup';
import ReceiverGroups from './receiverGroups';

const useStyles = makeStyles((theme: Theme) => ({
  and: {
    padding: theme.spacing(1),
  },
  values: {
    backgroundColor: fade(theme.palette.common.black, 0.1),
    borderRadius: theme.spacing(1),
    padding: theme.spacing(0.5),
    marginLeft: theme.spacing(0.5),
  },
  cardContent: {
    padding: theme.spacing(2),
    '&:last-child': {
      padding: theme.spacing(2),
    },
  },
}));

const TimeNotification: React.FC<TimeNotificationProps> = props => {
  const classes = useStyles();

  const [values, setValues] = useState<UpdateValues>({
    name: '',
    repeat: 'never',
    date: dayjs().set('m', 0).toDate(),
    message: '',
  });

  const setDefaultValues = (): void => {
    if (props.timeNotification) {
      setValues(prevState => ({
        ...prevState,
        name: props.timeNotification.name,
        repeat: props.timeNotification.repeat,
        data: props.timeNotification.date,
        message: props.timeNotification.message,
      }));
    }
  };

  useEffect(() => {
    setDefaultValues();
  }, [props.timeNotification]);

  return (
    <Paper title="Časová notifikace" loading={props.loading}>
      <Typography variant="h6">Základní informace</Typography>
      <Grid container spacing={2}>
        <Grid item xs={4}>
          <TextField
            label="Název"
            value={values.name}
            fullWidth
            onChange={e => {
              const name = e.target.value;
              setValues(prevState => ({ ...prevState, name }));
            }}
          />
        </Grid>
        <Grid item xs={4}>
          <FormControl fullWidth>
            <InputLabel id="repeatLabel">Opakovat</InputLabel>
            <Select
              value={values.repeat}
              labelId="repeatLabel"
              onChange={e => {
                const repeat = `${e.target.value}` as Repeat;
                setValues(prevState => ({
                  ...prevState,
                  repeat,
                }));
              }}
            >
              <MenuItem value="never">Nikdy (manuální odesílání)</MenuItem>
              <MenuItem value="daily">Denně</MenuItem>
              <MenuItem value="weekly">Týdně</MenuItem>
              <MenuItem value="monthly">Měsíčně</MenuItem>
              <MenuItem value="yearly">Ročně</MenuItem>
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={4}>
          <DateTimePicker
            variant="inline"
            ampm={false}
            value={values.date}
            fullWidth
            label="Datum"
            views={['date', 'hours']}
            onChange={date => {
              const value = dayjs(date).set('m', 0).toDate();
              setValues(prevState => ({
                ...prevState,
                date: value,
              }));
            }}
          />
        </Grid>
      </Grid>
      <Box pt={2}>
        <TextField
          label="Zpráva"
          fullWidth
          multiline
          rows={4}
          value={values.message}
          onChange={e => {
            const message = e.target.value;
            setValues(prevState => ({ ...prevState, message }));
          }}
        />
      </Box>
      <Box pt={2} display="flex" justifyContent="flex-end">
        <Button
          color="primary"
          variant="contained"
          onClick={() => props.onUpdate(values)}
        >
          Uložit
        </Button>
        <Box pl={2} />
        <Button
          color="secondary"
          variant="contained"
          onClick={setDefaultValues}
        >
          Zrušit
        </Button>
      </Box>
      <Box pt={2} />

      <Typography variant="h6">Příjemci</Typography>
      <ReceiverGroups
        receiverGroups={props.timeNotification?.receiverGroups || []}
      />
    </Paper>
  );
};

export default TimeNotification;
