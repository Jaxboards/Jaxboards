const categories = require('./categories');
const forums = require('./forums');
const member_groups = require('./member_groups');
const members = require('./members');
const sessions = require('./sessions');
const stats = require('./stats');

module.exports = function initResources({ sequelize, router, config }) {
  return [categories, forums, members, member_groups, sessions, stats].map(
    resource => {
      const instance = new resource({ sequelize, config });
      instance.addRoutes(router);
      return instance;
    }
  );
};
