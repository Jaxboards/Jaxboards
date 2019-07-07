const { BadRequest } = require('../utils/http-status');
const { NUM_POSTS_PER_PAGE } = require('../utils/constants');

const BaseResource = require('./resource');
const Member = require('../models/member').model;
const MemberGroup = require('../models/member_group').model;
const Post = require('../models/post').model;
const Topic = require('../models/topic').model;

class PostsResource extends BaseResource {
  getModel() {
    return super.getModel(Post);
  }

  getFindAllOptions(query = {}) {
    if (!query.tid) {
      throw new BadRequest('Missing required query parameter: tid');
    }

    const options = {
      include: [
        {
          model: super.getModel(Member),
          as: 'editor',
          attributes: ['id', 'display_name', 'group_id']
        },
        {
          model: super.getModel(Member),
          as: 'author',
          include: [
            {
              model: super.getModel(MemberGroup),
              as: 'group'
            }
          ]
        },
        {
          model: super.getModel(Topic),
          as: 'topic'
        }
      ],
      limit: NUM_POSTS_PER_PAGE,
      order: [['newtopic', 'DESC'], 'id'],
      where: {
        tid: query.tid
      }
    };

    // Paging
    if (query.page) {
      const page = parseInt(query.page, 10);
      if (page && !Number.isNaN(page)) {
        options.offset = NUM_POSTS_PER_PAGE * page;
      }
    }

    return options;
  }

  findAll(query = {}) {
    return this.getModel().findAll(this.getFindAllOptions(query));
  }

  findAndCountAll(query = {}) {
    return this.getModel().findAndCountAll(this.getFindAllOptions(query));
  }

  create(properties) {
    return this.getModel().create(properties);
  }
}

module.exports = PostsResource;
