const Sequelize = require('sequelize');

class PostModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    PostModel.init(
      {
        auth_id: Sequelize.INTEGER,
        post: Sequelize.TEXT,
        date: Sequelize.DATE,
        showsig: { type: Sequelize.BOOLEAN, defaultValue: true },
        showemotes: { type: Sequelize.BOOLEAN, defaultValue: true },
        tid: Sequelize.INTEGER,
        newtopic: Sequelize.BOOLEAN,
        ip: Sequelize.STRING(50),
        edit_date: Sequelize.DATE,
        editby: Sequelize.INTEGER,
        rating: Sequelize.INTEGER
      },
      {
        indexes: [
          {
            fields: ['tid']
          },
          {
            fields: ['auth_id']
          },
          {
            fields: ['ip']
          },
          {
            type: 'FULLTEXT',
            fields: ['post']
          }
        ],
        modelName: 'post',
        sequelize,
        timestamps: false
      }
    );
  },

  setAssociations({ Post, Member, Topic }) {
    Post.belongsTo(Member, { foreignKey: 'auth_id', as: 'author' });
    Post.belongsTo(Member, { foreignKey: 'editby', as: 'editor' });
    Post.belongsTo(Topic, { foreignKey: 'tid', onDelete: 'CASCADE' });
  },

  model: PostModel
};
