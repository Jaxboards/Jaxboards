class Resource {
  init({ sequelize, prefix }) {
    this.sequelize = sequelize;
    this.prefix = prefix;
    return this;
  }

  getModel(model) {
    return model.schema(this.prefix, { schemaDelimiter: '_' });
  }

  prefixTableNames(...tableNames) {
    return tableNames.map(name => `${this.prefix}_${name}`);
  }
}

module.exports = Resource;
