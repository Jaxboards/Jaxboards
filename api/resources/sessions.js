const BaseResource = require('./resource');
const Session = require('../models/session').model;

class SessionResource extends BaseResource {
  getModel() {
    return super.getModel(Session);
  }

  findAll() {
    return this.getModel().findAll();
  }

  addRoutes(router) {
    router.get('/sessions', async ctx => {
      ctx.body = await this.findAll();
    });
  }
}

module.exports = SessionResource;
