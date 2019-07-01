const Sequelize = require('sequelize');
const Forum = require('./forum').model;

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
        poll_type: Sequelize.ENUM('','single', 'multi'),
        summary: Sequelize.STRING,
        locked: Sequelize.BOOLEAN,
        date: Sequelize.DATE,
        op: Sequelize.INTEGER,
        cal_event: Sequelize.INTEGER
      },
      {
        indexes: [
          {
            fields: ['auth_id'],
          },
          {
            fields: ['lp_date'],
          },
          {
            fields: ['cal_event'],
          },
          {
            fields: ['lp_uid'],
          },
          {
            fields: ['fid'],
          },
          {
            fields: ['op'],
          },
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

    /**
     * TODO: Foreign Keys
  CONSTRAINT `blueprint_topics_ibfk_1` FOREIGN KEY (`lp_uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_topics_ibfk_2` FOREIGN KEY (`fid`) REFERENCES `blueprint_forums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blueprint_topics_ibfk_3` FOREIGN KEY (`auth_id`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blueprint_topics_ibfk_4` FOREIGN KEY (`op`) REFERENCES `blueprint_posts` (`id`) ON DELETE SET NULL
     */
  },

  setAssociations({ Forum, Topic }) {
    Topic.belongsTo(Forum, { foreignKey: 'fid' });
  },

  model: TopicModel
};
