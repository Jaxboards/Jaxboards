const BaseResource = require('./resource');
const Category = require('../models/category').model;

class CategoryResource extends BaseResource {
  getModel() {
    return super.getModel(Category);
  }

  getAll() {
    return this.getModel().findAll({
      order: ['order', 'title']
    });
  }

  addRoutes(router) {
    router.get('/categories', async ctx => {
      ctx.body = await this.getAll();
    });
  }
}

module.exports = CategoryResource;
