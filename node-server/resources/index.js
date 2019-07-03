const { inject } = require('../injections');

module.exports = function initResources({ sequelize, config }) {
  return [
    inject('resources/categories'),
    inject('resources/forums'),
    inject('resources/members'),
    inject('resources/member_groups'),
    inject('resources/sessions'),
    inject('resources/stats'),
    inject('resources/topics')
  ].map(resource => resource.init({ sequelize, config }));
};
