const { Op } = require('sequelize');
const BaseResource = require('./resource');
const Member = require('../models/member').model;

class MembersResource extends BaseResource {
  getModel() {
    return super.getModel(Member);
  }

  batchGet(ids) {
    return this.getModel().findAll({
      where: {
        id: {
          [Op.in]: ids.split(',').map(Number)
        }
      }
    });
  }

  find(id) {
    return this.getModel().findByPk(id);
  }

  addRoutes(router) {
    router.get('/members', async ctx => {
      if (ctx.query.ids) {
        ctx.body = await this.batchGet(ctx.query.ids);
      }
    });
    router.get('/member/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    });
  }
}

module.exports = MembersResource;
