import routes from './routes';

const appConfig = {
    appName: 'Směny',
    api: {
        clientUrl: 'https://smeny.pizzacomeback.cz/graphql',
        serverUrl: 'https://smeny.pizzacomeback.cz/graphql',
    },
    cookies: {
        token: 'shiftPlannerComebackToken',
        darkTheme: 'shiftPlannerComebackDarkTheme',
    },
    url: 'https://smeny.pizzacomeback.cz',
    notifications: {
        public:
            'BB9jcnYxW44QWadKz5QWc6II_HO4Q_J9jva1UlJDqX7lK1u94VmytCGKefJy1A-AYzHHAKBdNYfg0wBsjx-lPFw',
        version: '1',
    },
    devNavBar: false,
    routes,
};

export default appConfig;














