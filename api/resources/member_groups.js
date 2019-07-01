const BaseResource = require('./resource');
const MemberGroup = require('../models/member_group').model;

class MemberGroupsResource extends BaseResource {
  getModel() {
    return super.getModel(MemberGroup);
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
