const BaseResource = require('./resource');
const MemberGroups = require('../models/member_groups').model;

class MemberGroupsResource extends BaseResource {
  getModel() {
    return super.getModel(MemberGroups);
  }

  findAll(searchQuery = {}) {
    if (searchQuery.legend) {
      return this.getModel().findAll({
        attributes: ['id', 'title'],
        where: {
          legend: 1
        }
      });
    }

    return this.getModel().findAll();
  }

  addRoutes(router) {
    router.get('/member_groups', async ctx => {
      ctx.body = await this.findAll(ctx.query);
    });
  }
}

module.exports = MemberGroupsResource;
