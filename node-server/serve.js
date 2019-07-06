const Koa = require('koa');
const config = require('../config.json');

const { getDBConnection } = require('./utils/sequelize-helpers');
const injections = require('./injections');

const app = new Koa();

// Preload all dependencies
injections.loadAll();

getDBConnection(
  config.sql_db,
  config.sql_username,
  config.sql_password,
  config.sql_host,
  config.sql_prefix
);

const router = injections.inject('router');
app.use(router.routes()).use(router.allowedMethods());

// Logging
app.use(async (ctx, next) => {
  await next();
  const rt = ctx.response.get('X-Response-Time');
  console.log(`${ctx.method} ${ctx.url} - ${rt}`);
});

app.use(async (ctx, next) => {
  const start = Date.now();
  await next();
  const ms = Date.now() - start;
  ctx.set('X-Response-Time', `${ms}ms`);
});

app.listen(3000);
