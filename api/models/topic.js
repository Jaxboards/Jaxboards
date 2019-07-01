const Sequelize = require('sequelize');

class TopicModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    TopicModel.init(
      {
        title: Sequelize.STRING,
        subtitle: Sequelize.STRING,
        lp_uid: Sequelize.INTEGER,
        lp_date: Sequelize.DATE,
        fid: Sequelize.INTEGER,
        auth_id: Sequelize.INTEGER,
        replies: Sequelize.INTEGER,
        views: Sequelize.INTEGER,
        pinned: Sequelize.BOOLEAN,
        poll_choices: Sequelize.TEXT,
        poll_results: Sequelize.TEXT,
        poll_q: Sequelize.STRING,
        poll_type: Sequelize.ENUM('', 'single', 'multi'),
        summary: Sequelize.STRING,
        locked: Sequelize.BOOLEAN,
        date: Sequelize.DATE,
        op: Sequelize.INTEGER,
        cal_event: Sequelize.INTEGER
      },
      {
        indexes: [
          { fields: ['auth_id'] },
          { fields: ['lp_date'] },
          { fields: ['cal_event'] },
          { fields: ['lp_uid'] },
          { fields: ['fid'] },
          { fields: ['op'] },
          {
            type: 'FULLTEXT',
            fields: ['title']
          }
        ],
        modelName: 'topic',
        sequelize,
        timestamps: false
      }
    );
  },

  setAssociations({ Forum, Topic, Member }) {
    Topic.belongsTo(Forum, { foreignKey: 'fid' });
    Topic.belongsTo(Member, { foreignKey: 'lp_uid', as: 'last_poster' });
    Topic.belongsTo(Member, { foreignKey: 'auth_id', as: 'author' });
  },

  model: TopicModel
};
