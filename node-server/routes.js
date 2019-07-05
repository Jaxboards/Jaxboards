const Router = require('koa-router');
const { inject } = require('./injections');

module.exports = function routes() {
  const router = new Router();

  const findAll = resource => async ctx => {
    ctx.body = await resource.findAll(ctx.query);
  };
  const find = resource => async ctx => {
    ctx.body = await resource.find(ctx.params.id);
  };
  const renderControllers = ([
    parentController,
    ...childControllers
  ]) => async ctx => {
    ctx.body = await parentController.render(ctx, childControllers);
  };

  // api
  const apiRoutes = new Router();
  apiRoutes.get('/categories', findAll(inject('resources/categories')));
  apiRoutes.get('/category/:id', find(inject('resources/categories')));
  apiRoutes.get('/forums', findAll(inject('resources/forums')));
  apiRoutes.get('/forum/:id', find(inject('resources/forums')));
  apiRoutes.get('/member_groups', findAll(inject('resources/member_groups')));
  apiRoutes.get('/members', findAll(inject('resources/members')));
  apiRoutes.get('/member/:id', find(inject('resources/members')));
  apiRoutes.get('/sessions', findAll(inject('resources/sessions')));
  apiRoutes.get('/stats', findAll(inject('resources/stats')));
  apiRoutes.get('/topics', findAll(inject('resources/topics')));
  apiRoutes.get('/topic/:id', find(inject('resources/topics')));

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
    '/forum/:id',
    renderControllers([
      inject('controllers/application'),
      inject('controllers/forum')
    ])
  );
  return router;
};
