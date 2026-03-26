import routes from './routes';

const appConfig = {
    appName: 'DEV Směny',
    api: {
        clientUrl: 'https://dev.smeny.pizzacomeback.cz/graphql',
        serverUrl: 'https://dev.smeny.pizzacomeback.cz/graphql',
    },
    cookies: {
        token: 'shiftPlannerDevComebackToken',
        darkTheme: 'shiftPlannerDevComebackDarkTheme',
    },
    url: 'https://dev.smeny.pizzacomeback.cz',
    notifications: {
        public:
            'BB9jcnYxW44QWadKz5QWc6II_HO4Q_J9jva1UlJDqX7lK1u94VmytCGKefJy1A-AYzHHAKBdNYfg0wBsjx-lPFw',
        version: '1',
    },
    devNavBar: true,
    routes,
};

export default appConfig;














