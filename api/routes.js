const CategoriesResource = require('./resources/categories');
const ForumsResource = require('./resources/forums');
const MemberGroupsResource = require('./resources/member_groups');
const MembersResource = require('./resources/members');
const SessionsResource = require('./resources/sessions');
const StatsResource = require('./resources/stats');
const TopicsResource = require('./resources/topics');

module.exports = function routes(router) {
  const findAll = resource => async ctx => {
    ctx.body = await resource.findAll(ctx.query);
  };
  const find = resource => async ctx => {
    ctx.body = await resource.find(ctx.params.id);
  };

  router.get('/categories', findAll(CategoriesResource));
  router.get('/category/:id', find(CategoriesResource));
  router.get('/forums', findAll(ForumsResource));
  router.get('/forum/:id', find(ForumsResource));
  router.get('/member_groups', findAll(MemberGroupsResource));
  router.get('/members', findAll(MembersResource));
  router.get('/member/:id', find(MembersResource));
  router.get('/sessions', findAll(SessionsResource));
  router.get('/stats', findAll(StatsResource));
  router.get('/topics', findAll(TopicsResource));
  router.get('/topic/:id', find(TopicsResource));
};
