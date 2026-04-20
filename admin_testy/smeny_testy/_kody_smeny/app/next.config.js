const withOffline = require('next-offline');

const nextConfig = { dontAutoRegisterSw: true };

module.exports = withOffline(nextConfig);
