module.exports = class Resource {
  constructor({ sequelize, config }) {
    this.sequelize = sequelize;
    this.config = config;
  }

  tableName(name) {
    return `${this.config.sql_prefix}_${name}`;
  }
};
