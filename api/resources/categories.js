const BaseResource = require('./resource');
const Category = require('../models/category').model;

class CategoryResource extends BaseResource {
  getModel() {
    return super.getModel(Category);
  }

  find(id) {
    return this.getModel().findByPk(id);
  }

  findAll() {
    return this.getModel().findAll({
      order: ['order', 'title']
    });
  }

  addRoutes(router) {
    router.get('/category/:id', async ctx => {
      ctx.body = await this.find(ctx.params.id);
    });
    router.get('/categories', async ctx => {
      ctx.body = await this.findAll();
    });
  }
}

module.exports = CategoryResource;
