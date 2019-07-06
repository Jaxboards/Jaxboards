const { BadRequest } = require('../http-status');

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
const MEMBER_PROPS = ['id', 'display_name', 'group_id'];

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
          attributes: MEMBER_PROPS
        },
        {
          model: super.getModel(Member),
          as: 'author',
          attributes: MEMBER_PROPS
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

  getFindAllOptions(query = {}) {
    if (!query.fid) {
      throw new BadRequest('Missing required query parameter: fid');
    }

    const memberModel = super.getModel(Member);

    const options = {
      limit: NUM_TOPICS_PER_PAGE,
      order: [
        ['pinned', 'DESC'],
        ORDER_BY_MAP[query.orderBy] || ORDER_BY_MAP[0]
      ],
      include: [
        {
          model: memberModel,
          as: 'last_poster',
          attributes: MEMBER_PROPS
        },
        {
          model: memberModel,
          as: 'author',
          attributes: MEMBER_PROPS
        }
      ]
    };

    // Paging
    if (query.page) {
      const page = parseInt(query.page, 10);
      if (!Number.isNaN(page)) {
        options.offset = NUM_TOPICS_PER_PAGE * query.page;
      }
    }

    options.where = {
      fid: query.fid
    };

    return options;
  }

  async findAndCountAll(query = {}) {
    const options = this.getFindAllOptions(query);

    return this.getModel().findAndCountAll(options);
  }

  async findAll(query = {}) {
    const options = this.getFindAllOptions(query);

    return this.getModel().findAll(options);
  }
}

module.exports = TopicResource;
