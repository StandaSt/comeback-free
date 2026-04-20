const apiConfig = {
    port: 4000,
    hash: {
        saltRounds: 6,
    },
    jwt: {
        secret: '',
        expiresIn: '4h',
    },
    sendgrid: {
        apiKey: '',
        from: 'smeny.comeback@gmail.com',
        sendEmail: true,
        templates: {
            afterRegistration: 'd-fed96e4628f04692a4b1bfac7a39b136',
        },
    },
};

export default apiConfig;
