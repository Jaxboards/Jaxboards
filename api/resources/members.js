const { Op } = require('sequelize');
const BaseResource = require('./resource');
const Member = require('../models/member').model;

class MembersResource extends BaseResource {
  getModel() {
    return super.getModel(Member);
  }

  find(id) {
    return this.getModel().findByPk(id);
  }

  findAll({ query }) {
    if (query.ids) {
      const ids = query.ids.split(',').map(Number);
      return this.getModel().findAll({
        where: {
          id: {
            [Op.in]: ids
          }
        }
      });
    }

    return null;
  }

  addRoutes(router) {
    router.get('/members', async ctx => {
      ctx.body = await this.findAll(ctx.query);
    });
    router.get('/member/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    });
  }
}

module.exports = MembersResource;
