const BaseResource = require('./resource');
const Session = require('../models/session').model;

class SessionResource extends BaseResource {
  getModel() {
    return super.getModel(Session);
  }

  findAll() {
    return this.getModel().findAll();
  }
}

module.exports = new SessionResource();
