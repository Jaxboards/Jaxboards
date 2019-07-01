module.exports = class Resource {
  constructor({ sequelize, config }) {
    this.sequelize = sequelize;
    this.config = config;
  }

  getModel(model) {
    return model.schema(this.config.sql_prefix, { schemaDelimiter: '_' });
  }

  prefixTableNames(...tableNames) {
    return tableNames.map(name => `${this.config.sql_prefix}_${name}`);
  }
};
