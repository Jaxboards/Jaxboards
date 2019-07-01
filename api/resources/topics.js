const BaseResource = require('./resource');
const Topic = require('../models/topic').model;
const Member = require('../models/member').model;

const NUM_TOPICS_PER_PAGE = 20;
const ORDER_BY_MAP = [
  ['lp_date', 'DESC'],
  ['lp_date', 'ASC'],
  ['id', 'DESC'],
  ['id', 'ASC'],
  ['title', 'DESC'],
  ['title', 'ASC']
];

class TopicResource extends BaseResource {
  getModel() {
    return super.getModel(Topic);
  }

  findAll(query = {}) {
    const options = {
      limit: NUM_TOPICS_PER_PAGE,
      order: [
        ['pinned', 'DESC'],
        ORDER_BY_MAP[query.orderBy] || ORDER_BY_MAP[0]
      ]
    };

    // Paging
    if (query.page) {
      const page = parseInt(query.page, 10);
      if (!Number.isNaN(page)) {
        options.offset = NUM_TOPICS_PER_PAGE * query.page;
      }
    }

    // Find by forum ID
    if (query.fid) {
      options.where = {
        fid: query.fid
      };
    }

    return this.getModel().findAll({
      include: [
        {
          model: super.getModel(Member),
          as: 'last_poster',
          attributes: ['display_name', 'group_id']
        },
        {
          model: super.getModel(Member),
          as: 'author',
          attributes: ['display_name', 'group_id']
        }
      ],
      ...options
    });
  }

  addRoutes(router) {
    router.get('/topics', async ctx => {
      ctx.body = await this.findAll(ctx.query);
    });

    router.get('/topic/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    });
  }
}

module.exports = TopicResource;
