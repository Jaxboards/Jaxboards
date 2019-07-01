const Sequelize = require('sequelize');

class MemberModel extends Sequelize.Model {}

module.exports = {
  init(sequelize) {
    MemberModel.init(
      {
        name: Sequelize.STRING,
        pass: Sequelize.STRING,
        email: Sequelize.STRING(50),
        sig: Sequelize.TEXT,
        posts: {
          type: Sequelize.NUMBER,
          defaultValue: 0
        },
        group_id: Sequelize.INTEGER,
        avatar: Sequelize.STRING,
        usertitle: Sequelize.STRING,
        join_date: Sequelize.DATE,
        last_visit: Sequelize.DATE,
        contact_skype: Sequelize.STRING(50),
        contact_yim: Sequelize.STRING(50),
        contact_msn: Sequelize.STRING(50),
        contact_gtalk: Sequelize.STRING(50),
        contact_aim: Sequelize.STRING(50),
        website: Sequelize.STRING,
        birthdate: Sequelize.DATE,
        about: Sequelize.TEXT,
        display_name: Sequelize.STRING(30),
        full_name: Sequelize.STRING(50),
        contact_steam: Sequelize.STRING(50),
        location: Sequelize.STRING(100),
        gender: Sequelize.ENUM('', 'male', 'female', 'other'),
        friends: Sequelize.TEXT,
        enemies: Sequelize.TEXT,
        sound_shout: Sequelize.BOOLEAN,
        sound_im: Sequelize.BOOLEAN,
        sound_pm: Sequelize.BOOLEAN,
        sound_postinmytopic: Sequelize.BOOLEAN,
        sound_postinsubscribedtopic: Sequelize.BOOLEAN,
        notify_pm: Sequelize.BOOLEAN,
        notify_postinmytopic: Sequelize.BOOLEAN,
        notify_postinsubscribedtopic: Sequelize.BOOLEAN,
        ucpnotepad: Sequelize.TEXT,
        skin_id: Sequelize.INTEGER,
        contact_twitter: Sequelize.STRING,
        email_settings: Sequelize.BOOLEAN,
        nowordfilter: Sequelize.BOOLEAN,
        ip: Sequelize.STRING(50),
        mod: Sequelize.BOOLEAN,
        wysiwyg: Sequelize.BOOLEAN,
      },
      {
        indexes: [
          {
            fields: ['display_name'],
          },
          {
            fields: ['group_id'],
          },
        ],
        defaultScope: {
          // Private fields
          attributes: {
            exclude: ['pass', 'email', 'ip', 'ucpnotepad']
          }
        },
        modelName: 'member',
        sequelize,
        timestamps: false
      }
    );

    // TODO: Foreign Keys?
    // CONSTRAINT `blueprint_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `blueprint_member_groups` (`id`) ON DELETE SET NULL
  },

  setAssociations({ Member, MemberGroups }) {
    Member.belongsTo(MemberGroups, { foreignKey: 'group_id' });
  },

  model: MemberModel
};
