const category = require('./category');

module.exports = function initModels(sequelize) {
  [
    category
  ].forEach(model => model.init(sequelize));
};
