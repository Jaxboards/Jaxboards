const modelModules = {
  Category: require('./category'),
  Forum: require('./forum'),
  Member: require('./member'),
  MemberGroups: require('./member_groups'),
  Topic: require('./topic'),
  Session: require('./session')
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
