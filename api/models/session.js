const Sequelize = require('sequelize');

class SessionModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    SessionModel.init(
      {
        id: {
          type: Sequelize.STRING(200),
          allowNull: false,
          primaryKey: true
        },
        uid: Sequelize.INTEGER,
        ip: Sequelize.STRING(50),
        vars: Sequelize.TEXT,
        last_update: Sequelize.DATE,
        last_action: Sequelize.DATE,
        runonce: Sequelize.TEXT,
        location: Sequelize.TEXT,
        users_online_cache: Sequelize.TEXT,
        is_bot: Sequelize.BOOLEAN,
        buddy_list_cache: Sequelize.TEXT,
        location_verbose: Sequelize.STRING(100),
        useragent: Sequelize.STRING,
        forumsread: Sequelize.BLOB,
        topicsread: Sequelize.BLOB,
        read_date: Sequelize.DATE,
        hide: Sequelize.BOOLEAN
      },
      {
        indexes: [{ fields: ['uid'] }],
        defaultScope: {
          attributes: {
            // Hide IP by default
            exclude: ['ip']
          }
        },
        modelName: 'session',
        freezeTableName: true,
        sequelize,
        timestamps: false
      }
    );
  },

  setAssociations({ Session, Member }) {
    Session.belongsTo(Member, { foreignKey: 'uid', onDelete: 'CASCADE' });
  },

  model: SessionModel
};
