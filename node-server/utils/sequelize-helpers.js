const Sequelize = require('sequelize');
const { inject, queryByType, TYPE } = require('../injections');

function initResources({ sequelize, prefix }) {
  return queryByType(TYPE.RESOURCE).map(resource =>
    inject(resource).init({ sequelize, prefix })
  );
}

// Converts "member_group" to "MemberGroup"
function camelize(name) {
  return (
    name[0].toUpperCase() +
    name.slice(1).replace(/[-_](\w)/, match => match[1].toUpperCase())
  );
}

function initModels(sequelize) {
  const modelModules = queryByType(TYPE.MODEL);
  // First initialize all models
  const models = modelModules.reduce((result, injectionPath) => {
    const module = inject(injectionPath);
    module.init(sequelize);
    result[camelize(injectionPath.slice(TYPE.MODEL.length + 1))] = module.model;
    return result;
  }, {});

  // Then set up their relationships
  modelModules.forEach(injectionPath => {
    const module = inject(injectionPath);
    if (module.setAssociations) {
      module.setAssociations(models);
    }
  });
}

function getDBConnection(db, user, pass, host, prefix = '') {
  // Get a DB connection
  const sequelize = new Sequelize(db, user, pass, {
    host,
    dialect: 'mysql'
  });

  // Initialize data layer
  initModels(sequelize);
  initResources({
    sequelize,
    prefix
  });

  return sequelize;
}

module.exports = { getDBConnection };
