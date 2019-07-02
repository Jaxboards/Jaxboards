module.exports = class Resource {
  init({ sequelize, config }) {
    this.sequelize = sequelize;
    this.config = config;
    return this;
  }

  getModel(model) {
    return model.schema(this.config.sql_prefix, { schemaDelimiter: '_' });
  }

  prefixTableNames(...tableNames) {
    return tableNames.map(name => `${this.config.sql_prefix}_${name}`);
  }
};
