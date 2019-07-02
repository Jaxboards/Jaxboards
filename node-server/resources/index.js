const categories = require('./categories');
const forums = require('./forums');
const memberGroups = require('./member_groups');
const members = require('./members');
const sessions = require('./sessions');
const stats = require('./stats');
const topics = require('./topics');

module.exports = function initResources({ sequelize, config }) {
  return [
    categories,
    forums,
    members,
    memberGroups,
    sessions,
    stats,
    topics
  ].map(resource => resource.init({ sequelize, config }));
};
