const BaseResource = require('./resource');
const Category = require('../models/category').model;
const Forum = require('../models/forum').model;
const Topic = require('../models/topic').model;
const Member = require('../models/member').model;

class CategoryResource extends BaseResource {
  getModel() {
    return super.getModel(Category);
  }

  find(id) {
    return this.getModel().findByPk(id);
  }

  findAll(query = {}) {
    const options = {};

    if (query.full) {
      options.include = [
        {
          model: super.getModel(Forum),
          include: [
            {
              model: super.getModel(Topic),
              as: 'last_topic'
            },
            {
              model: super.getModel(Member),
              as: 'last_poster'
            }
          ],
          // Filter out subforums
          where: {
            path: ''
          }
        }
      ];
    }

    return this.getModel().findAll({
      order: ['order', 'title'],
      ...options
    });
  }
}

module.exports = CategoryResource;
