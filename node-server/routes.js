const Router = require('koa-router');
const { inject } = require('./injections');
const CategoriesResource = require('./resources/categories');
const ForumsResource = require('./resources/forums');
const MemberGroupsResource = require('./resources/member_groups');
const MembersResource = require('./resources/members');
const SessionsResource = require('./resources/sessions');
const StatsResource = require('./resources/stats');
const TopicsResource = require('./resources/topics');

module.exports = function routes() {
  const router = new Router();

  const findAll = resource => async ctx => {
    ctx.body = await resource.findAll(ctx.query);
  };
  const find = resource => async ctx => {
    ctx.body = await resource.find(ctx.params.id);
  };
  const renderController = controller => async ctx => {
    ctx.body = await controller.render(ctx.query);
  };

  // api
  const apiRoutes = new Router();
  apiRoutes.get('/categories', findAll(CategoriesResource));
  apiRoutes.get('/category/:id', find(CategoriesResource));
  apiRoutes.get('/forums', findAll(ForumsResource));
  apiRoutes.get('/forum/:id', find(ForumsResource));
  apiRoutes.get('/member_groups', findAll(MemberGroupsResource));
  apiRoutes.get('/members', findAll(MembersResource));
  apiRoutes.get('/member/:id', find(MembersResource));
  apiRoutes.get('/sessions', findAll(SessionsResource));
  apiRoutes.get('/stats', findAll(StatsResource));
  apiRoutes.get('/topics', findAll(TopicsResource));
  apiRoutes.get('/topic/:id', find(TopicsResource));

  // Top level routes
  router.use('/api', apiRoutes.routes(), apiRoutes.allowedMethods());

  router.get('/', renderController(inject('controllers/index')));
  return router;
};
