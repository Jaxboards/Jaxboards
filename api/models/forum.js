const Sequelize = require('sequelize');
const Category = require('./category').model;
const Topic = require('./topic').model;
const Member = require('./member').model;

class ForumModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    ForumModel.init(
      {
        cat_id: Sequelize.INTEGER,
        lp_date: Sequelize.DATE,
        lp_tid: Sequelize.INTEGER,
        lp_topic: Sequelize.STRING,
        lp_uid: Sequelize.INTEGER,
        mods: Sequelize.STRING,
        nocount: Sequelize.BOOLEAN,
        order: Sequelize.INTEGER,
        orderby: Sequelize.INTEGER,
        path: Sequelize.STRING,
        perms: Sequelize.STRING.BINARY,
        posts: Sequelize.INTEGER,
        redirect: Sequelize.STRING,
        redirects: Sequelize.INTEGER,
        show_ledby: Sequelize.BOOLEAN,
        show_sub: Sequelize.INTEGER,
        subtitle: Sequelize.TEXT,
        title: Sequelize.STRING,
        topics: Sequelize.INTEGER,
        trashcan: Sequelize.BOOLEAN
      },
      {
        indexes: [
          {
            fields: ['cat_id']
          },
          {
            fields: ['lp_uid']
          },
          {
            fields: ['lp_tid']
          }
        ],
        modelName: 'forum',
        sequelize,
        timestamps: false
      }
    );
  },

  setAssociations({ Forum, Category, Topic }) {
    Forum.belongsTo(Category, { foreignKey: 'cat_id' });
    Forum.belongsTo(Topic, { foreignKey: 'lp_tid' });
    Forum.belongsTo(Member, { foreignKey: 'lp_uid', as: 'last_poster' });
  },

  model: ForumModel
};
