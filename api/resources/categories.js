const BaseResource = require('./resource');

class CategoryResource extends BaseResource {
  getAll() {
    const { sequelize } = this;
    const [categories] = sequelize.prefixTableNames(['categories']);

    return sequelize.query(
      `SELECT \`id\`,\`title\`,\`order\` FROM ${categories} ORDER BY \`order\`,\`title\` ASC`,
      { type: sequelize.QueryTypes.SELECT }
    );
  }

  addRoutes(router) {
    router.get('/categories', async ctx => {
      ctx.body = await this.getAll();
    });
  }
}

module.exports = CategoryResource;
