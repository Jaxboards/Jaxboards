const Koa = require('koa');
const koaBody = require('koa-body');
const passport = require('koa-passport');
const session = require('koa-session');
const { Strategy } = require('passport-local');

const config = require('../config.json');

const { getDBConnection } = require('./utils/sequelize-helpers');
const injections = require('./injections');

const app = new Koa();

// Parse POST body
app.use(koaBody());

// Preload all dependencies
injections.loadAll();

getDBConnection(
  config.sql_db,
  config.sql_username,
  config.sql_password,
  config.sql_host,
  config.sql_prefix
);

// Session
app.keys = ['secret'];
app.use(session({}, app));

// Authentication
passport.serializeUser((user, cb) => cb(null, user.id));
passport.deserializeUser((id, cb) => {
  injections
    .inject('resources/members')
    .getAuthenticatedUserById(id)
    .then(user => cb(null, user))
    .catch(err => cb(err));
});
passport.use(
  new Strategy((username, password, cb) => {
    injections
      .inject('resources/members')
      .getAuthenticatedUser(username, password)
      .then(user => cb(null, user))
      .catch(err => cb(err));
  })
);
app.use(passport.initialize());
app.use(passport.session());

// Routing
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
