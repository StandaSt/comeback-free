const { createServer } = require('http');
const { join } = require('path');
const { parse } = require('url');
const fs = require('fs');
const path = require('path');

const chalk = require('chalk');
const next = require('next');

const app = next({ dev: process.env.NODE_ENV !== 'production' });
const handle = app.getRequestHandler();

app.prepare().then(() => {
  fs.copyFileSync(
    path.join(app.distDir, 'service-worker.js'),
    path.join(__dirname, 'public', 'pwaService.js'),
  );

  const port = process.env.PORT || 3000;

  createServer((req, res) => {
    const parsedUrl = parse(req.url, true);
    const { pathname } = parsedUrl;

    // handle GET request to /service-worker.js
    if (pathname === '/service-worker.js') {
      const filePath = join(__dirname, '.next', pathname);

      app.serveStatic(req, res, filePath);
    } else {
      handle(req, res, parsedUrl);
    }
  }).listen(port, () => {
    // eslint-disable-next-line no-console
    console.log(
      chalk.green('ready'),
      `- started server on http://localhost:${port}`,
    );
  });
});
