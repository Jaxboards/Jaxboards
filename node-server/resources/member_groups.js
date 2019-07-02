const BaseResource = require('./resource');
const MemberGroup = require('../models/member_group').model;

class MemberGroupsResource extends BaseResource {
  getModel() {
    return super.getModel(MemberGroup);
  }

  findAll(query = {}) {
    if (query.legend) {
      return this.getModel().findAll({
        attributes: ['id', 'title'],
        where: {
          legend: 1
        }
      });
    }

    return this.getModel().findAll();
  }
}

module.exports = new MemberGroupsResource();
