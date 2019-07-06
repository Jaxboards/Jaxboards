const { BadRequest } = require('../http-status');

const BaseResource = require('./resource');
const Member = require('../models/member').model;
const MemberGroup = require('../models/member_group').model;
const Post = require('../models/post').model;
const Topic = require('../models/topic').model;

class PostsResource extends BaseResource {
  getModel() {
    return super.getModel(Post);
  }

  findAll(query = {}) {
    if (!query.tid) {
      throw new BadRequest('Missing required query parameter: tid');
    }

    return this.getModel().findAll({
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
      order: [['newtopic', 'DESC'], 'id'],
      where: {
        tid: query.tid
      }
    });
  }
}

module.exports = PostsResource;
