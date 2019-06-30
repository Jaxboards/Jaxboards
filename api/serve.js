const Koa = require('koa');
const Router = require('koa-router');
const Sequelize = require('sequelize');
const config = require('../config.json');

const initResources = require('./resources');

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

const app = new Koa();
const router = new Router();

// HACK: Add table prefix helper to Sequelize.
// This can possibly be removed after we convert the MySQL queries to ORM
sequelize.prefixTableNames = function prefixTableNames(tableNames) {
  return tableNames.map(name => `${config.sql_prefix}_${name}`);
}

initResources({
  sequelize,
  router,
  config
});

app
  .use(router.routes())
  .use(router.allowedMethods());

// // logger

// app.use(async (ctx, next) => {
//   await next();
//   const rt = ctx.response.get('X-Response-Time');
//   console.log(`${ctx.method} ${ctx.url} - ${rt}`);
// });

// // x-response-time

// app.use(async (ctx, next) => {
//   const start = Date.now();
//   await next();
//   const ms = Date.now() - start;
//   ctx.set('X-Response-Time', `${ms}ms`);
// });

// // response

// app.use(async ctx => {
//   ctx.body = JSON.stringify(config);
// });

app.listen(3000);
