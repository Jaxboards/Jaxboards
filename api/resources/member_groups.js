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
    return [];
  }

  addRoutes(router) {
    router.get('/member_groups', async ctx => {
      if (ctx.query.legend) {
        ctx.body = await this.findAll({
          legend: true
        });
      }
    });
  }
}

module.exports = MemberGroupsResource;
