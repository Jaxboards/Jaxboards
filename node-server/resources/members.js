const bcrypt = require('bcrypt');
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

  getAuthenticatedUserById(id) {
    return this.getModel()
      .scope('full')
      .findByPk(id);
  }

  async getAuthenticatedUser(name, password) {
    const PHP_PREFIX = /^\$2y\$/;
    const NODE_PREFIX = '$2a$';
    const user = await this.getModel()
      .scope('full')
      .findOne({
        where: {
          name
        }
      });

    if (
      user &&
      bcrypt.compareSync(password, user.pass.replace(PHP_PREFIX, NODE_PREFIX))
    ) {
      return user;
    }

    return false;
  }
}

module.exports = MembersResource;
