/* eslint-disable global-require */

const modelModules = {
  Category: require('./category'),
  Forum: require('./forum'),
  Member: require('./member'),
  MemberGroup: require('./member_group'),
  Post: require('./post'),
  Session: require('./session'),
  Topic: require('./topic')
};

module.exports = function initModels(sequelize) {
  // First initialize all models
  const models = Object.keys(modelModules).reduce((result, key) => {
    modelModules[key].init(sequelize);
    result[key] = modelModules[key].model;
    return result;
  }, {});

  // Then set up their relationships
  Object.keys(modelModules).forEach(key => {
    if (modelModules[key].setAssociations) {
      modelModules[key].setAssociations(models);
    }
  });
};
