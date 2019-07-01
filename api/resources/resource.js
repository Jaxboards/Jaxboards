module.exports = class Resource {
  constructor({ sequelize, config }) {
    this.sequelize = sequelize;
    this.config = config;
  }

  getModel(model) {
    return model.schema(this.config.sql_prefix, { schemaDelimiter: '_' });
  }
};
