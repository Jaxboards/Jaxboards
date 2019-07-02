const Sequelize = require('sequelize');

class CategoryModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    CategoryModel.init(
      {
        title: Sequelize.STRING,
        order: {
          type: Sequelize.INTEGER,
          allowNull: false,
          defaultValue: 0
        }
      },
      {
        modelName: 'category',
        sequelize,
        timestamps: false
      }
    );
  },

  setAssociations({ Forum, Category }) {
    Category.hasMany(Forum, { foreignKey: 'cat_id' });
  },

  model: CategoryModel
};
