const { Op } = require('sequelize');
const BaseResource = require('./resource');
const Forum = require('../models/forum').model;
const Category = require('../models/category').model;
const Member = require('../models/member').model;

class ForumResource extends BaseResource {
  getModel() {
    return super.getModel(Forum);
  }

  find(id) {
    return this.getModel().findByPk(id, {
      include: [
        {
          model: super.getModel(Category),
          attributes: ['title']
        }
      ]
    });
  }

  findAll(query = {}) {

    // TODO: Don't build our own queries
    let where;
    if (query.lp_date) {
      where = {
        lp_date: {
          [Op.gte]: query.lp_date
        }
      };
    } else if (query.path) {
      where = {
        path: {
          [Op.or]: {
            [Op.eq]: query.path,
            [Op.endsWith]: ` ${query.path}`
          }
        }
      };
    }

    return this.getModel().findAll({
      include: [
        {
          model: super.getModel(Member),
          as: 'last_poster',
          attributes: ['display_name', 'group_id']
        }
      ],
      where
    });
  }

  addRoutes(router) {
    router.get('/forums', async ctx => {
      ctx.body = await this.findAll(ctx.query);
    });

    router.get('/forum/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    });
  }
}

module.exports = ForumResource;
