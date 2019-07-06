const Router = require('koa-router');
const { inject, register } = require('./injections');

module.exports = function routes() {
  const router = new Router();

  const respond = response => async ctx => {
    try {
      ctx.body = await response(ctx);
    } catch (e) {
      ctx.throw(e.status, e.message);
    }
  };
  const findAll = resource =>
    respond(async ctx => inject(resource).findAll(ctx.query));

  const find = resource =>
    respond(async ctx => inject(resource).find(ctx.params.id));

  const renderControllers = ([
    parentController,
    ...childControllers
  ]) => async ctx => {
    ctx.body = await parentController.render(ctx, childControllers);
  };

  // api
  const apiRoutes = new Router();
  apiRoutes.get('/categories', findAll('resources/categories'));
  apiRoutes.get('/category/:id', find('resources/categories'));
  apiRoutes.get('/forums', findAll('resources/forums'));
  apiRoutes.get('/forum/:id', find('resources/forums'));
  apiRoutes.get('/member_groups', findAll('resources/member_groups'));
  apiRoutes.get('/members', findAll('resources/members'));
  apiRoutes.get('/member/:id', find('resources/members'));
  apiRoutes.get('/sessions', findAll('resources/sessions'));
  apiRoutes.get('/stats', findAll('resources/stats'));
  apiRoutes.get('/topics', findAll('resources/topics'));
  apiRoutes.get('/topic/:id', find('resources/topics'));
  apiRoutes.get('/posts', findAll('resources/posts'));

  // Top level routes
  router.use('/api', apiRoutes.routes(), apiRoutes.allowedMethods());

  router.get(
    '/',
    renderControllers([
      inject('controllers/application'),
      inject('controllers/index')
    ])
  );

  router.get(
    'forum',
    '/forum/:id',
    renderControllers([
      inject('controllers/application'),
      inject('controllers/forum')
    ])
  );

  router.get(
    'topic',
    '/topic/:id',
    renderControllers([
      inject('controllers/application'),
      inject('controllers/topic')
    ])
  );

  router.get(
    'user',
    '/user/:id',
    renderControllers([
      inject('controllers/application'),
      inject('controllers/user')
    ])
  );

  register('router', router);

  return router;
};
