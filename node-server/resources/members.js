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

  findAll(query = {}) {
    // Batch get
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
}

module.exports = new MembersResource();
