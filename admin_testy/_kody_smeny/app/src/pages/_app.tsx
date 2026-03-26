import dayjsUtils from '@date-io/dayjs';
import CssBaseline from '@material-ui/core/CssBaseline';
import { ThemeProvider as ThemeProviderPrefab } from '@material-ui/core/styles';
import { MuiPickersUtilsProvider } from '@material-ui/pickers';
import App from 'next/app';
import Head from 'next/head';
import { useCookies } from 'react-cookie';
import appConfig from '@shift-planner/shared/config/app';
import { Provider } from 'react-redux';
import React from 'react';

import dayjsLocale from 'lib/dayjs/locale';
import theme, { darkTheme } from 'lib/materialui/theme';
import SnackbarProvider from 'lib/notistack';
import store from 'redux/reducers';

import registerServiceWorkers, {
  checkSubscription,
} from '../components/registerServiceWorkers';

const ThemProvider = (props: any) => {
  const [cookies] = useCookies();

  let prefersDarkMode = false;
  if (process.browser) {
    prefersDarkMode = cookies[appConfig.cookies.darkTheme] === 'true';
  }

  return (
    <ThemeProviderPrefab
      theme={prefersDarkMode ? darkTheme : theme}
      {...props}
    />
  );
};

class MyApp extends App<{ store: any }> {
  componentDidMount(): void {
    const jssStyles = document.querySelector('#jss-server-side');
    if (jssStyles) {
      jssStyles.parentElement.removeChild(jssStyles);
    }
    if (process.browser) {
      registerServiceWorkers();
    }
  }

  render(): JSX.Element {
    checkSubscription();

    const { Component, pageProps } = this.props;

    return (
      <>
        <Head>
          <title>{appConfig.appName}</title>
          <meta
            name="viewport"
            content="minimum-scale=1, initial-scale=1, width=device-width"
          />
        </Head>
        <Provider store={store}>
          <ThemProvider>
            <SnackbarProvider>
              <MuiPickersUtilsProvider utils={dayjsUtils} locale={dayjsLocale}>
                <CssBaseline />
                <Component {...pageProps} />
              </MuiPickersUtilsProvider>
            </SnackbarProvider>
          </ThemProvider>
        </Provider>
      </>
    );
  }
}

export default MyApp;
