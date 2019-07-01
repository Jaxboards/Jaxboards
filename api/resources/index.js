const categories = require('./categories');
const forums = require('./forums');
const memberGroups = require('./member_groups');
const members = require('./members');
const sessions = require('./sessions');
const stats = require('./stats');
const topics = require('./topics');

module.exports = function initResources({ sequelize, router, config }) {
  return [
    categories,
    forums,
    members,
    memberGroups,
    sessions,
    stats,
    topics
  ].map(Resource => {
    const instance = new Resource({ sequelize, config });
    instance.addRoutes(router);
    return instance;
  });
};
