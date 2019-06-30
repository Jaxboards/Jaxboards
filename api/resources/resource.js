module.exports = class Resource {
  constructor({ sequelize, config }) {
    this.sequelize = sequelize;
    this.config = config;
  }
};
