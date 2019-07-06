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
    let where;

    if (query.tid) {
      where = {
        tid: query.tid
      };
    } else {
      // Don't allow fetchAll for now with no finder
      return {};
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
      where
    });
  }
}

module.exports = PostsResource;
