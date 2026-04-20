import routes from './routes';

const appConfig = {
  appName: 'Směny',
  api: {
    clientUrl: 'http://localhost:4000/graphql',
    serverUrl: 'http://localhost:4000/graphql',
  },
  cookies: {
    token: 'shiftPlannerDevComebackToken',
    darkTheme: 'shiftPlannerDevComebackDarkTheme',
  },
  url: 'http://localhost:3000',
  notifications: {
    public:
      'BB9jcnYxW44QWadKz5QWc6II_HO4Q_J9jva1UlJDqX7lK1u94VmytCGKefJy1A-AYzHHAKBdNYfg0wBsjx-lPFw',
    version: '1',
  },
  devNavBar: false,
  routes,
};

export default appConfig;
