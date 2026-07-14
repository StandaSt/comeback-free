import { blue, pink, red } from '@material-ui/core/colors';
import { createMuiTheme } from '@material-ui/core/styles';

const theme = createMuiTheme({
  palette: {
    primary: {
      main: blue[500],
    },
    secondary: {
      main: pink.A400,
    },
    error: {
      main: red.A400,
    },
    background: {
      default: '#f9f9f9',
    },
  },
});

export const darkTheme = createMuiTheme({
  palette: {
    type: 'dark',
    primary: {
      main: blue[400],
    },
    secondary: {
      main: pink[400],
    },
    error: {
      main: red[400],
    },
  },
});

export default theme;
