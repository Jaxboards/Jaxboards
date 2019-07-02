const Koa = require('koa');
const Router = require('koa-router');
const Sequelize = require('sequelize');
const config = require('../config.json');

const initResources = require('./resources');
const initModels = require('./models');
const routes = require('./routes');

const app = new Koa();
const router = new Router();

// Get a DB connection
const sequelize = new Sequelize(
  config.sql_db,
  config.sql_username,
  config.sql_password,
  {
    host: config.sql_host,
    dialect: 'mysql'
  }
);

// Initialize data layer
initModels(sequelize);
initResources({
  sequelize,
  config
});

// Set up Routing
routes(router);

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

app.use(router.routes()).use(router.allowedMethods());

app.listen(3000);
