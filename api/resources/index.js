const forums = require('./forums');

module.exports = function index({ sequelize, router, config }) {
  return [
    forums
  ].map(resource => {
    const instance = new resource({ sequelize, config });
    instance.addRoutes(router);
    return instance;
  });
}
