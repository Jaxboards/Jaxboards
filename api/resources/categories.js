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
}

module.exports = new CategoryResource();
