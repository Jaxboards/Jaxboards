const BaseResource = require('./resource');
const Topic = require('../models/topic').model;
const Member = require('../models/member').model;
const Forum = require('../models/forum').model;
const Category = require('../models/category').model;

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

  find(id) {
    return this.getModel().findByPk(id, {
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
        },
        {
          model: super.getModel(Forum),
          as: 'forum',
          include: [
            {
              model: super.getModel(Category),
              as: 'category'
            }
          ]
        }
      ]
    });
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

    const memberModel = super.getModel(Member);

    return this.getModel().findAll({
      include: [
        {
          model: memberModel,
          as: 'last_poster',
          attributes: ['display_name', 'group_id']
        },
        {
          model: memberModel,
          as: 'author',
          attributes: ['display_name', 'group_id']
        }
      ],
      ...options
    });
  }
}

module.exports = new TopicResource();
